<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Analytics\Application\Query;

use App\Analytics\Domain\Port\ClickHouseClientPort;
use App\Analytics\Domain\Port\QueryCachePort;
use App\Analytics\Domain\Query\FilterCompiler;
use App\Analytics\Domain\Query\Grain;
use App\Analytics\Domain\Query\ParameterBag;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Interval;
use App\Analytics\Domain\ValueObject\Metric;

/**
 * Returns one or more metrics bucketed over a time interval
 * (OpenAPI `getTimeSeries`).
 *
 * Event-grained metrics are bucketed from `events`; session-grained metrics
 * (bounce rate, average duration) are bucketed from `sessions` by `started_at`.
 * Buckets are merged on the response side keyed by bucket timestamp so a sparse
 * grain still aligns with the dense one.
 */
final readonly class TimeSeriesHandler
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
        private SegmentResolver $segmentResolver,
    ) {
    }

    /**
     * @return array{
     *   interval: string,
     *   period: array{from: string, to: string},
     *   series: list<array{bucket: string, metrics: array<string, float|int>}>
     * }
     */
    public function __invoke(TimeSeriesQuery $timeSeries): array
    {
        $query = $timeSeries->query;
        $interval = $timeSeries->resolvedInterval();
        $metrics = $query->metrics === [] ? Metric::defaults() : $query->metrics;

        $groups = $this->segmentResolver->resolveGroups($query->siteId, $query->segmentId, $query->inlineFilters);

        $eventClause = $this->eventStatement($query, $interval, $groups);
        $needsSessions = $this->hasSessionMetric($metrics);
        $sessionClause = $needsSessions ? $this->sessionStatement($query, $interval, $groups) : null;

        $clauses = $sessionClause === null ? [$eventClause] : [$eventClause, $sessionClause];
        $key = QueryFingerprint::fromClauses('timeseries:' . $interval->value, $clauses);

        /** @var list<array{bucket: string, metrics: array<string, float|int>}> $buckets */
        $buckets = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($eventClause, $sessionClause, $metrics): array {
            $eventRows = $this->clickHouse->select($eventClause->sql, $eventClause->bindings);
            $sessionRows = $sessionClause === null ? [] : $this->clickHouse->select($sessionClause->sql, $sessionClause->bindings);

            return $this->mergeBuckets($eventRows, $sessionRows, $metrics);
        });

        return [
            'interval' => $interval->value,
            'period' => [
                'from' => $query->dateRange->fromDate(),
                'to' => $query->dateRange->toDate(),
            ],
            'series' => $buckets,
        ];
    }

    /**
     * @param list<FilterSet> $groups
     */
    private function eventStatement(AnalyticsQuery $query, Interval $interval, array $groups): SqlClause
    {
        $params = new ParameterBag();
        $site = $params->bind('UUID', (string) $query->siteId, 'site');
        $start = $params->bind('DateTime64(3)', $query->dateRange->fromDate() . ' 00:00:00', 'start');
        $end = $params->bind('DateTime64(3)', $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end');
        $where = (new FilterCompiler($params))->compileGroups(Grain::Events, ...$groups);

        $sql = sprintf(
            'SELECT %s(timestamp) AS bucket, '
            . 'uniq(visitor_id) AS visitors, '
            . "countIf(event_name = 'pageview') AS pageviews, "
            . 'uniq(session_id) AS sessions, '
            . 'count() AS events, '
            . "uniqIf(session_id, event_name = 'conversion') AS converting_sessions "
            . 'FROM statflow.events '
            . 'WHERE site_id = %s AND timestamp >= %s AND timestamp < %s AND (%s) '
            . 'GROUP BY bucket ORDER BY bucket',
            $interval->clickHouseBucketFunction(),
            $site,
            $start,
            $end,
            $where->sql,
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @param list<FilterSet> $groups
     */
    private function sessionStatement(AnalyticsQuery $query, Interval $interval, array $groups): SqlClause
    {
        $params = new ParameterBag();
        $site = $params->bind('UUID', (string) $query->siteId, 'site');
        $start = $params->bind('DateTime64(3)', $query->dateRange->fromDate() . ' 00:00:00', 'start');
        $end = $params->bind('DateTime64(3)', $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end');
        $where = (new FilterCompiler($params))->compileGroups(Grain::Sessions, ...$groups);

        $sql = sprintf(
            'SELECT %s(started_at) AS bucket, '
            . 'count() AS total_sessions, '
            . 'countIf(bounce = 1) AS bounced_sessions, '
            . 'avg(duration_s) AS avg_duration '
            . 'FROM statflow.sessions FINAL '
            . 'WHERE site_id = %s AND started_at >= %s AND started_at < %s AND (%s) '
            . 'GROUP BY bucket ORDER BY bucket',
            $interval->clickHouseBucketFunction(),
            $site,
            $start,
            $end,
            $where->sql,
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @param list<array<string, int|float|string|null>> $eventRows
     * @param list<array<string, int|float|string|null>> $sessionRows
     * @param list<Metric>                               $metrics
     *
     * @return list<array{bucket: string, metrics: array<string, float|int>}>
     */
    private function mergeBuckets(array $eventRows, array $sessionRows, array $metrics): array
    {
        $eventByBucket = [];
        foreach ($eventRows as $row) {
            $eventByBucket[(string) ($row['bucket'] ?? '')] = $row;
        }

        $sessionByBucket = [];
        foreach ($sessionRows as $row) {
            $sessionByBucket[(string) ($row['bucket'] ?? '')] = $row;
        }

        // A sparse grain (e.g. sessions) may carry buckets the other grain has
        // none of; iterate the full union so no bucket is silently dropped.
        $buckets = array_keys($eventByBucket + $sessionByBucket);
        sort($buckets);

        $out = [];
        foreach ($buckets as $bucket) {
            $out[] = [
                'bucket' => $this->isoBucket($bucket),
                'metrics' => $this->projectBucket($eventByBucket[$bucket] ?? [], $sessionByBucket[$bucket] ?? [], $metrics),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, int|float|string|null> $eventRow
     * @param array<string, int|float|string|null> $sessionRow
     * @param list<Metric>                         $metrics
     *
     * @return array<string, float|int>
     */
    private function projectBucket(array $eventRow, array $sessionRow, array $metrics): array
    {
        $sessions = (int) ($eventRow['sessions'] ?? 0);
        $converting = (int) ($eventRow['converting_sessions'] ?? 0);
        $totalSessions = (int) ($sessionRow['total_sessions'] ?? 0);
        $bounced = (int) ($sessionRow['bounced_sessions'] ?? 0);

        $values = [
            Metric::Visitors->value => (int) ($eventRow['visitors'] ?? 0),
            Metric::Pageviews->value => (int) ($eventRow['pageviews'] ?? 0),
            Metric::Sessions->value => $sessions,
            Metric::Events->value => (int) ($eventRow['events'] ?? 0),
            Metric::ConversionRate->value => $sessions > 0 ? round($converting * 100 / $sessions, 2) : 0.0,
            Metric::BounceRate->value => $totalSessions > 0 ? round($bounced * 100 / $totalSessions, 2) : 0.0,
            Metric::AvgDuration->value => round((float) ($sessionRow['avg_duration'] ?? 0), 2),
        ];

        $out = [];
        foreach ($metrics as $metric) {
            $out[$metric->value] = $values[$metric->value];
        }

        return $out;
    }

    /**
     * @param list<Metric> $metrics
     */
    private function hasSessionMetric(array $metrics): bool
    {
        foreach ($metrics as $metric) {
            if ($metric->isSessionScoped()) {
                return true;
            }
        }

        return false;
    }

    private function isoBucket(string $clickHouseTimestamp): string
    {
        return str_replace(' ', 'T', $clickHouseTimestamp) . 'Z';
    }
}
