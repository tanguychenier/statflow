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
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\Metric;

/**
 * Computes summary metrics for a period (OpenAPI `getAggregateMetrics`):
 * visitors, pageviews, sessions, bounce rate, average duration, events, and
 * conversion rate, with an optional comparison period and change percentages.
 */
final readonly class AggregateMetricsHandler
{
    /**
     * Cached aggregates for closed (historical) ranges are stable; a short TTL
     * bounds staleness for ranges that include today.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
        private SegmentResolver $segmentResolver,
        private AggregateMetricsQueryBuilder $builder,
    ) {
    }

    /**
     * @return array{
     *   period: array{from: string, to: string},
     *   metrics: array<string, float|int>,
     *   comparison?: array{
     *     period: array{from: string, to: string},
     *     metrics: array<string, float|int>,
     *     change_pct: array<string, float|null>
     *   }
     * }
     */
    public function __invoke(AnalyticsQuery $query): array
    {
        $primary = $this->computeForRange($query, $query->dateRange);
        $primaryMetrics = $this->projectMetrics($primary, $query->metrics);

        $response = [
            'period' => $this->periodPayload($query->dateRange),
            'metrics' => $primaryMetrics,
        ];

        $comparisonRange = $query->comparisonRange();
        if ($comparisonRange !== null) {
            $comparison = $this->computeForRange($query->withDateRange($comparisonRange), $comparisonRange);
            $comparisonMetrics = $this->projectMetrics($comparison, $query->metrics);

            $response['comparison'] = [
                'period' => $this->periodPayload($comparisonRange),
                'metrics' => $comparisonMetrics,
                'change_pct' => $this->changePercentages($primaryMetrics, $comparisonMetrics),
            ];
        }

        return $response;
    }

    /**
     * @return array<string, float>
     */
    private function computeForRange(AnalyticsQuery $query, DateRange $range): array
    {
        $groups = $this->segmentResolver->resolveGroups(
            $query->siteId,
            $query->segmentId,
            $query->inlineFilters,
        );

        $eventClause = $this->builder->eventStatement($query->siteId, $range, $groups);
        $sessionClause = $this->builder->sessionStatement($query->siteId, $range, $groups);

        $key = QueryFingerprint::fromClauses('aggregate', [$eventClause, $sessionClause]);

        /** @var array<string, float> $result */
        $result = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($eventClause, $sessionClause): array {
            $eventRow = $this->clickHouse->select($eventClause->sql, $eventClause->bindings)[0] ?? [];
            $sessionRow = $this->clickHouse->select($sessionClause->sql, $sessionClause->bindings)[0] ?? [];

            return $this->rawMetrics($eventRow, $sessionRow);
        });

        return $result;
    }

    /**
     * @param array<string, int|float|string|null> $eventRow
     * @param array<string, int|float|string|null> $sessionRow
     *
     * @return array<string, float>
     */
    private function rawMetrics(array $eventRow, array $sessionRow): array
    {
        $sessions = (int) ($eventRow['sessions'] ?? 0);
        $convertingSessions = (int) ($eventRow['converting_sessions'] ?? 0);
        $totalSessions = (int) ($sessionRow['total_sessions'] ?? 0);
        $bounced = (int) ($sessionRow['bounced_sessions'] ?? 0);

        return [
            Metric::Visitors->value => (float) (int) ($eventRow['visitors'] ?? 0),
            Metric::Pageviews->value => (float) (int) ($eventRow['pageviews'] ?? 0),
            Metric::Sessions->value => (float) $sessions,
            Metric::Events->value => (float) (int) ($eventRow['events'] ?? 0),
            Metric::ConversionRate->value => $sessions > 0
                ? round($convertingSessions * 100 / $sessions, 2)
                : 0.0,
            Metric::BounceRate->value => $totalSessions > 0
                ? round($bounced * 100 / $totalSessions, 2)
                : 0.0,
            Metric::AvgDuration->value => round((float) ($sessionRow['avg_duration'] ?? 0), 2),
        ];
    }

    /**
     * @param array<string, float> $raw
     * @param list<Metric>         $metrics
     *
     * @return array<string, float|int>
     */
    private function projectMetrics(array $raw, array $metrics): array
    {
        $selected = $metrics === [] ? Metric::defaults() : $metrics;
        $out = [];
        foreach ($selected as $metric) {
            $value = $raw[$metric->value] ?? 0.0;
            $out[$metric->value] = $this->isCountMetric($metric) ? (int) $value : $value;
        }

        return $out;
    }

    /**
     * @param array<string, float|int> $current
     * @param array<string, float|int> $previous
     *
     * @return array<string, float|null>
     */
    private function changePercentages(array $current, array $previous): array
    {
        $out = [];
        foreach ($current as $name => $value) {
            $base = (float) ($previous[$name] ?? 0);
            $out[$name] = $base > 0.0 ? round(((float) $value - $base) * 100 / $base, 2) : null;
        }

        return $out;
    }

    private function isCountMetric(Metric $metric): bool
    {
        return match ($metric) {
            Metric::Visitors, Metric::Pageviews, Metric::Sessions, Metric::Events => true,
            default => false,
        };
    }

    /**
     * @return array{from: string, to: string}
     */
    private function periodPayload(DateRange $range): array
    {
        return [
            'from' => $range->fromDate(),
            'to' => $range->toDate(),
        ];
    }
}
