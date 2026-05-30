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

use App\Analytics\Domain\Exception\FunnelNotFound;
use App\Analytics\Domain\Model\Funnel;
use App\Analytics\Domain\Port\ClickHouseClientPort;
use App\Analytics\Domain\Port\FunnelRepositoryPort;
use App\Analytics\Domain\Port\QueryCachePort;

/**
 * Calculates conversion through a saved funnel's ordered steps
 * (OpenAPI `queryFunnel`).
 *
 * Steps are pre-materialised into `funnel_events`; ClickHouse's `windowFunnel`
 * yields the deepest consecutive step each visitor reached within the
 * conversion window. Per-step entered/converted/dropped counts are derived from
 * the level histogram (clickhouse.md §8).
 */
final readonly class FunnelHandler
{
    private const CACHE_TTL_SECONDS = 300;

    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
        private FunnelRepositoryPort $funnels,
    ) {
    }

    /**
     * @return array{
     *   period: array{from: string, to: string},
     *   conversion_window_days: int,
     *   steps: list<array<string, mixed>>
     * }
     */
    public function __invoke(FunnelQuery $query): array
    {
        $funnel = $this->funnels->find($query->siteId, $query->funnelId);
        if ($funnel === null) {
            throw FunnelNotFound::withId((string) $query->funnelId);
        }

        $levelClause = $this->levelHistogramStatement($funnel, $query);
        $timingClause = $this->stepTimingStatement($funnel, $query);
        $key = QueryFingerprint::fromClauses('funnel:' . $query->funnelId, [$levelClause, $timingClause]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($levelClause, $timingClause, $funnel): array {
            $histogram = $this->levelHistogram($levelClause);
            $timings = $this->stepTimings($timingClause);

            return $this->assembleSteps($funnel, $histogram, $timings);
        });

        return [
            'period' => [
                'from' => $query->dateRange->fromDate(),
                'to' => $query->dateRange->toDate(),
            ],
            'conversion_window_days' => $query->conversionWindowDays,
            'steps' => $rows,
        ];
    }

    private function levelHistogramStatement(Funnel $funnel, FunnelQuery $query): SqlClause
    {
        $bindings = [
            'site' => (string) $query->siteId,
            'funnel' => (string) $query->funnelId,
            'start' => $query->dateRange->fromDate() . ' 00:00:00',
            'end' => $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00',
            'window' => $query->conversionWindowDays * self::SECONDS_PER_DAY,
        ];

        $conditions = [];
        for ($i = 0; $i < $funnel->stepCount(); ++$i) {
            $conditions[] = sprintf('step_index = %d', $i);
        }

        $sql = sprintf(
            'SELECT level, count() AS users FROM ('
            . 'SELECT visitor_id, windowFunnel({window:UInt32})(timestamp, %s) AS level '
            . 'FROM statflow.funnel_events '
            . 'WHERE site_id = {site:UUID} AND funnel_id = {funnel:UUID} '
            . 'AND timestamp >= {start:DateTime64(3)} AND timestamp < {end:DateTime64(3)} '
            . 'GROUP BY visitor_id'
            . ') GROUP BY level',
            implode(', ', $conditions),
        );

        return new SqlClause($sql, $bindings);
    }

    private function stepTimingStatement(Funnel $funnel, FunnelQuery $query): SqlClause
    {
        $bindings = [
            'site' => (string) $query->siteId,
            'funnel' => (string) $query->funnelId,
            'start' => $query->dateRange->fromDate() . ' 00:00:00',
            'end' => $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00',
        ];

        // Pivot each visitor's first-touch timestamp per step into one row, then
        // average the gap from the previous step across visitors that reached
        // both. One SELECT column per consecutive step pair.
        $firstTouch = [];
        for ($i = 0; $i < $funnel->stepCount(); ++$i) {
            $firstTouch[] = sprintf('minIf(timestamp, step_index = %d) AS t%d', $i, $i);
        }

        $gaps = [];
        for ($i = 1; $i < $funnel->stepCount(); ++$i) {
            $gaps[] = sprintf(
                "(%d, if(t%d > toDateTime64(0, 3) AND t%d >= t%d, dateDiff('second', t%d, t%d), NULL))",
                $i,
                $i - 1,
                $i,
                $i - 1,
                $i - 1,
                $i,
            );
        }

        // No timing for a single-step funnel (defensive; min steps is 2).
        if ($gaps === []) {
            return new SqlClause('SELECT 0 AS step_index, 0 AS avg_gap_s WHERE 0', $bindings);
        }

        $sql = sprintf(
            'SELECT pair.1 AS step_index, avg(pair.2) AS avg_gap_s FROM ('
            . 'SELECT %s FROM statflow.funnel_events '
            . 'WHERE site_id = {site:UUID} AND funnel_id = {funnel:UUID} '
            . 'AND timestamp >= {start:DateTime64(3)} AND timestamp < {end:DateTime64(3)} '
            . 'GROUP BY visitor_id'
            . ') ARRAY JOIN [%s] AS pair WHERE pair.2 IS NOT NULL GROUP BY step_index',
            implode(', ', $firstTouch),
            implode(', ', $gaps),
        );

        return new SqlClause($sql, $bindings);
    }

    /**
     * @return array<int, int> level => user count
     */
    private function levelHistogram(SqlClause $clause): array
    {
        $rows = $this->clickHouse->select($clause->sql, $clause->bindings);
        $histogram = [];
        foreach ($rows as $row) {
            $histogram[(int) ($row['level'] ?? 0)] = (int) ($row['users'] ?? 0);
        }

        return $histogram;
    }

    /**
     * @return array<int, float> step_index => avg seconds from previous step
     */
    private function stepTimings(SqlClause $clause): array
    {
        $rows = $this->clickHouse->select($clause->sql, $clause->bindings);
        $timings = [];
        foreach ($rows as $row) {
            $timings[(int) ($row['step_index'] ?? 0)] = round((float) ($row['avg_gap_s'] ?? 0), 2);
        }

        return $timings;
    }

    /**
     * @param array<int, int>   $histogram
     * @param array<int, float> $timings
     *
     * @return list<array<string, mixed>>
     */
    private function assembleSteps(Funnel $funnel, array $histogram, array $timings): array
    {
        $stepCount = $funnel->stepCount();

        // reachedAtLeast[i] = number of visitors whose deepest level >= i.
        $reachedAtLeast = [];
        for ($i = 0; $i <= $stepCount; ++$i) {
            $sum = 0;
            foreach ($histogram as $level => $users) {
                if ($level >= $i) {
                    $sum += $users;
                }
            }
            $reachedAtLeast[$i] = $sum;
        }

        $steps = [];
        foreach ($funnel->steps as $index => $step) {
            $entered = $reachedAtLeast[$index];
            $converted = $reachedAtLeast[$index + 1] ?? 0;

            $steps[] = [
                'step_index' => $step->stepIndex,
                'label' => $step->label,
                'event_name' => $step->displayEventName(),
                'entered' => $entered,
                'converted' => $converted,
                'dropped' => max(0, $entered - $converted),
                'conversion_rate_pct' => $entered > 0 ? round($converted * 100 / $entered, 2) : 0.0,
                'avg_time_to_convert_seconds' => $index === 0 ? 0.0 : ($timings[$step->stepIndex] ?? 0.0),
            ];
        }

        return $steps;
    }
}
