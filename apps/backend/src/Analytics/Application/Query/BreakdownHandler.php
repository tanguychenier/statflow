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
use App\Analytics\Domain\ValueObject\Metric;

/**
 * Breaks metrics down by a single dimension and returns the top rows
 * (OpenAPI `getBreakdown`). Replaces separate top-pages / top-sources /
 * top-devices / top-countries endpoints.
 *
 * Session-grained dimensions (entry/exit page) are grouped from `sessions`;
 * everything else from `events`. Session-scoped metrics (bounce rate, average
 * duration) requested against an event-grained dimension are sourced from a
 * second `sessions` query grouped by the same dimension and merged by value, so
 * they are computed on the session grain rather than silently zeroed. The sort
 * metric drives ORDER BY; `total_rows` reports the distinct cardinality before
 * the limit so the UI can paginate.
 */
final readonly class BreakdownHandler
{
    private const CACHE_TTL_SECONDS = 120;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
        private SegmentResolver $segmentResolver,
    ) {
    }

    /**
     * @return array{
     *   property: string,
     *   period: array{from: string, to: string},
     *   rows: list<array{value: string, metrics: array<string, float|int>}>,
     *   total_rows: int
     * }
     */
    public function __invoke(BreakdownQuery $breakdown): array
    {
        $query = $breakdown->query;
        $metrics = $query->metrics === [] ? Metric::defaults() : $query->metrics;
        $groups = $this->segmentResolver->resolveGroups($query->siteId, $query->segmentId, $query->inlineFilters);

        if ($breakdown->property->isSessionScoped()) {
            $primary = $this->sessionStatement($breakdown, $groups);
            $sessionSidecar = null;
        } else {
            $primary = $this->eventStatement($breakdown, $groups);
            $sessionSidecar = $this->needsSessionSidecar($breakdown, $metrics)
                ? $this->sessionMetricsStatement($breakdown, $groups)
                : null;
        }

        $clauses = $sessionSidecar === null ? [$primary] : [$primary, $sessionSidecar];
        $key = QueryFingerprint::fromClauses('breakdown:' . $breakdown->property->value, $clauses);

        /** @var array{rows: list<array{value: string, metrics: array<string, float|int>}>, total_rows: int} $payload */
        $payload = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($primary, $sessionSidecar, $breakdown, $metrics): array {
            $rows = $this->clickHouse->select($primary->sql, $primary->bindings);
            $sessionRows = $sessionSidecar === null ? [] : $this->clickHouse->select($sessionSidecar->sql, $sessionSidecar->bindings);

            return $this->projectRows($rows, $sessionRows, $breakdown, $metrics);
        });

        return [
            'property' => $breakdown->property->value,
            'period' => [
                'from' => $query->dateRange->fromDate(),
                'to' => $query->dateRange->toDate(),
            ],
            'rows' => $payload['rows'],
            'total_rows' => $payload['total_rows'],
        ];
    }

    /**
     * @param list<FilterSet> $groups
     */
    private function eventStatement(BreakdownQuery $breakdown, array $groups): SqlClause
    {
        $params = new ParameterBag();
        $query = $breakdown->query;
        $site = $params->bind('UUID', (string) $query->siteId, 'site');
        $start = $params->bind('DateTime64(3)', $query->dateRange->fromDate() . ' 00:00:00', 'start');
        $end = $params->bind('DateTime64(3)', $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end');
        $where = (new FilterCompiler($params))->compileGroups(Grain::Events, ...$groups);

        $column = $breakdown->property->eventColumn();
        $orderExpr = $this->orderExpression($breakdown->sortBy, false);

        // total_rows is the distinct cardinality of the dimension before the
        // limit. ClickHouse rejects count(DISTINCT ...) inside a window, so the
        // grouping happens in a CTE and the outer query counts the grouped rows
        // with a plain count() OVER ().
        $sql = sprintf(
            'WITH grouped AS ('
            . 'SELECT toString(%s) AS value, '
            . 'uniq(visitor_id) AS visitors, '
            . "countIf(event_name = 'pageview') AS pageviews, "
            . 'uniq(session_id) AS sessions, '
            . 'count() AS events, '
            . "uniqIf(session_id, event_name = 'conversion') AS converting_sessions "
            . 'FROM statflow.events '
            . 'WHERE site_id = %s AND timestamp >= %s AND timestamp < %s AND (%s) '
            . 'GROUP BY value'
            . ') '
            . 'SELECT value, visitors, pageviews, sessions, events, converting_sessions, '
            . 'count() OVER () AS total_rows '
            . 'FROM grouped ORDER BY %s %s LIMIT %s',
            $column,
            $site,
            $start,
            $end,
            $where->sql,
            $orderExpr,
            $breakdown->sortDescending ? 'DESC' : 'ASC',
            $params->bind('UInt32', $breakdown->limit, 'lim'),
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @param list<FilterSet> $groups
     */
    private function sessionStatement(BreakdownQuery $breakdown, array $groups): SqlClause
    {
        $params = new ParameterBag();
        $query = $breakdown->query;
        $site = $params->bind('UUID', (string) $query->siteId, 'site');
        $start = $params->bind('DateTime64(3)', $query->dateRange->fromDate() . ' 00:00:00', 'start');
        $end = $params->bind('DateTime64(3)', $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end');
        $where = (new FilterCompiler($params))->compileGroups(Grain::Sessions, ...$groups);

        $column = $breakdown->property->sessionColumn();
        $orderExpr = $this->orderExpression($breakdown->sortBy, true);

        $sql = sprintf(
            'WITH grouped AS ('
            . 'SELECT toString(%s) AS value, '
            . 'uniq(visitor_id) AS visitors, '
            . 'sum(pageview_count) AS pageviews, '
            . 'count() AS sessions, '
            . 'sum(event_count) AS events, '
            . 'countIf(bounce = 1) AS bounced_sessions, '
            . 'avg(duration_s) AS avg_duration '
            . 'FROM statflow.sessions FINAL '
            . 'WHERE site_id = %s AND started_at >= %s AND started_at < %s AND (%s) '
            . 'GROUP BY value'
            . ') '
            . 'SELECT value, visitors, pageviews, sessions, events, bounced_sessions, avg_duration, '
            . 'count() OVER () AS total_rows '
            . 'FROM grouped ORDER BY %s %s LIMIT %s',
            $column,
            $site,
            $start,
            $end,
            $where->sql,
            $orderExpr,
            $breakdown->sortDescending ? 'DESC' : 'ASC',
            $params->bind('UInt32', $breakdown->limit, 'lim'),
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * Session-grained metrics for an event-grained breakdown, grouped by the same
     * dimension so they can be merged onto the event rows by value.
     *
     * @param list<FilterSet> $groups
     */
    private function sessionMetricsStatement(BreakdownQuery $breakdown, array $groups): SqlClause
    {
        $params = new ParameterBag();
        $query = $breakdown->query;
        $site = $params->bind('UUID', (string) $query->siteId, 'site');
        $start = $params->bind('DateTime64(3)', $query->dateRange->fromDate() . ' 00:00:00', 'start');
        $end = $params->bind('DateTime64(3)', $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end');
        $where = (new FilterCompiler($params))->compileGroups(Grain::Sessions, ...$groups);

        $column = $breakdown->property->sessionColumn();

        $sql = sprintf(
            'SELECT toString(%s) AS value, '
            . 'count() AS total_sessions, '
            . 'countIf(bounce = 1) AS bounced_sessions, '
            . 'avg(duration_s) AS avg_duration '
            . 'FROM statflow.sessions FINAL '
            . 'WHERE site_id = %s AND started_at >= %s AND started_at < %s AND (%s) '
            . 'GROUP BY value',
            $column,
            $site,
            $start,
            $end,
            $where->sql,
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @param list<Metric> $metrics
     */
    private function needsSessionSidecar(BreakdownQuery $breakdown, array $metrics): bool
    {
        foreach ($metrics as $metric) {
            if ($metric->isSessionScoped()) {
                return true;
            }
        }

        return $breakdown->sortBy->isSessionScoped();
    }

    private function orderExpression(Metric $metric, bool $sessionGrain): string
    {
        return match ($metric) {
            Metric::Visitors => 'visitors',
            Metric::Pageviews => 'pageviews',
            Metric::Sessions => 'sessions',
            Metric::Events => 'events',
            Metric::ConversionRate => $sessionGrain ? 'sessions' : 'converting_sessions',
            Metric::BounceRate => $sessionGrain ? 'bounced_sessions' : 'sessions',
            Metric::AvgDuration => $sessionGrain ? 'avg_duration' : 'sessions',
        };
    }

    /**
     * @param list<array<string, int|float|string|null>> $rows
     * @param list<array<string, int|float|string|null>> $sessionRows
     * @param list<Metric>                               $metrics
     *
     * @return array{rows: list<array{value: string, metrics: array<string, float|int>}>, total_rows: int}
     */
    private function projectRows(array $rows, array $sessionRows, BreakdownQuery $breakdown, array $metrics): array
    {
        $sessionGrain = $breakdown->property->isSessionScoped();

        $sessionByValue = [];
        foreach ($sessionRows as $sessionRow) {
            $sessionByValue[(string) ($sessionRow['value'] ?? '')] = $sessionRow;
        }

        $out = [];
        $totalRows = 0;
        foreach ($rows as $row) {
            $totalRows = (int) ($row['total_rows'] ?? $totalRows);
            $value = (string) ($row['value'] ?? '');
            $out[] = [
                'value' => $value,
                'metrics' => $this->projectRowMetrics($row, $sessionByValue[$value] ?? [], $metrics, $sessionGrain),
            ];
        }

        return [
            'rows' => $out,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * @param array<string, int|float|string|null> $row
     * @param array<string, int|float|string|null> $sessionRow
     * @param list<Metric>                         $metrics
     *
     * @return array<string, float|int>
     */
    private function projectRowMetrics(array $row, array $sessionRow, array $metrics, bool $sessionGrain): array
    {
        $sessions = (int) ($row['sessions'] ?? 0);
        $converting = (int) ($row['converting_sessions'] ?? 0);

        // On the session grain bounce rate is taken against the group's own
        // session count; on the event grain it is taken against the merged
        // sessions-table count for the same dimension value.
        $bounced = (int) ($sessionGrain ? ($row['bounced_sessions'] ?? 0) : ($sessionRow['bounced_sessions'] ?? 0));
        $bounceBase = $sessionGrain ? $sessions : (int) ($sessionRow['total_sessions'] ?? 0);
        $avgDuration = $sessionGrain ? ($row['avg_duration'] ?? 0) : ($sessionRow['avg_duration'] ?? 0);

        $values = [
            Metric::Visitors->value => (int) ($row['visitors'] ?? 0),
            Metric::Pageviews->value => (int) ($row['pageviews'] ?? 0),
            Metric::Sessions->value => $sessions,
            Metric::Events->value => (int) ($row['events'] ?? 0),
            Metric::ConversionRate->value => $sessions > 0 ? round($converting * 100 / $sessions, 2) : 0.0,
            Metric::BounceRate->value => $bounceBase > 0 ? round($bounced * 100 / $bounceBase, 2) : 0.0,
            Metric::AvgDuration->value => round((float) $avgDuration, 2),
        ];

        $out = [];
        foreach ($metrics as $metric) {
            $out[$metric->value] = $values[$metric->value];
        }

        return $out;
    }
}
