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
use App\Analytics\Domain\ValueObject\HeatmapType;

/**
 * Click/scroll heatmap data for one page (OpenAPI `queryHeatmap`).
 *
 * Click heatmaps read the pre-bucketed `heatmap_stats` grid (200x200 cells of
 * percentage coordinates); scroll heatmaps read `scroll_depth_stats` bands and
 * report the cumulative share of visitors that reached at least each depth.
 */
final readonly class HeatmapHandler
{
    private const CACHE_TTL_SECONDS = 600;

    private const GRID_DIVISOR = 2.0;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
    ) {
    }

    /**
     * @return array{
     *   pathname: string,
     *   period: array{from: string, to: string},
     *   heatmap_type: string,
     *   total_events: int,
     *   click_points: list<array<string, mixed>>,
     *   scroll_depths?: list<array<string, mixed>>
     * }
     */
    public function __invoke(HeatmapQuery $query): array
    {
        $cacheKind = 'heatmap:' . $query->type->value;
        $clause = $query->type === HeatmapType::Scroll
            ? $this->scrollStatement($query)
            : $this->clickStatement($query);
        $key = QueryFingerprint::fromClauses($cacheKind . ':' . $query->pathname, [$clause]);

        /** @var array{pathname: string, period: array{from: string, to: string}, heatmap_type: string, total_events: int, click_points: list<array<string, mixed>>, scroll_depths?: list<array<string, mixed>>} $result */
        $result = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($clause, $query): array {
            $rows = $this->clickHouse->select($clause->sql, $clause->bindings);

            return $query->type === HeatmapType::Scroll
                ? $this->scrollResponse($query, $rows)
                : $this->clickResponse($query, $rows);
        });

        return $result;
    }

    private function clickStatement(HeatmapQuery $query): SqlClause
    {
        $sql = 'SELECT bucket_x, bucket_y, sum(click_count) AS clicks '
            . 'FROM statflow.heatmap_stats '
            . 'WHERE site_id = {site:UUID} AND day >= {start:Date} AND day <= {end:Date} '
            . 'AND ' . $this->pathnamePredicate($query->pathname) . ' '
            . 'GROUP BY bucket_x, bucket_y ORDER BY clicks DESC';

        return new SqlClause($sql, $this->bucketBindings($query));
    }

    private function scrollStatement(HeatmapQuery $query): SqlClause
    {
        $sql = 'SELECT depth_band, uniqMerge(visitor_count) AS visitors '
            . 'FROM statflow.scroll_depth_stats '
            . 'WHERE site_id = {site:UUID} AND day >= {start:Date} AND day <= {end:Date} '
            . 'AND ' . $this->pathnamePredicate($query->pathname) . ' '
            . 'GROUP BY depth_band ORDER BY depth_band';

        return new SqlClause($sql, $this->bucketBindings($query));
    }

    /**
     * @return array<string, scalar>
     */
    private function bucketBindings(HeatmapQuery $query): array
    {
        return [
            'site' => (string) $query->siteId,
            'start' => $query->dateRange->fromDate(),
            'end' => $query->dateRange->toDate(),
            'pathname' => $this->pathnameOperand($query->pathname),
        ];
    }

    private function pathnamePredicate(string $pathname): string
    {
        return str_contains($pathname, '*')
            ? 'pathname LIKE {pathname:String}'
            : 'pathname = {pathname:String}';
    }

    private function pathnameOperand(string $pathname): string
    {
        return str_contains($pathname, '*') ? str_replace('*', '%', $pathname) : $pathname;
    }

    /**
     * @param list<array<string, int|float|string|null>> $rows
     *
     * @return array<string, mixed>
     */
    private function clickResponse(HeatmapQuery $query, array $rows): array
    {
        $maxClicks = 0;
        $total = 0;
        foreach ($rows as $row) {
            $clicks = (int) ($row['clicks'] ?? 0);
            $total += $clicks;
            $maxClicks = max($maxClicks, $clicks);
        }

        $points = array_map(
            static function (array $row) use ($maxClicks): array {
                $clicks = (int) ($row['clicks'] ?? 0);

                return [
                    'x_pct' => round((int) ($row['bucket_x'] ?? 0) / self::GRID_DIVISOR, 2),
                    'y_pct' => round((int) ($row['bucket_y'] ?? 0) / self::GRID_DIVISOR, 2),
                    'count' => $clicks,
                    'weight' => $maxClicks > 0 ? round($clicks / $maxClicks, 4) : 0.0,
                ];
            },
            $rows,
        );

        return [
            'pathname' => $query->pathname,
            'period' => [
                'from' => $query->dateRange->fromDate(),
                'to' => $query->dateRange->toDate(),
            ],
            'heatmap_type' => HeatmapType::Click->value,
            'total_events' => $total,
            'click_points' => $points,
        ];
    }

    /**
     * @param list<array<string, int|float|string|null>> $rows
     *
     * @return array<string, mixed>
     */
    private function scrollResponse(HeatmapQuery $query, array $rows): array
    {
        $byBand = [];
        foreach ($rows as $row) {
            $byBand[(int) ($row['depth_band'] ?? 0)] = (int) ($row['visitors'] ?? 0);
        }

        // Distinct visitor envelope: a visitor counted at band B reached at least
        // every band <= B. The denominator is the union of all bands, which for a
        // scroll-depth distribution is the band-0 (page-load) cohort.
        $totalVisitors = array_sum($byBand);

        $depths = [];
        for ($band = 0; $band <= 100; $band += 10) {
            $reached = 0;
            foreach ($byBand as $depth => $visitors) {
                if ($depth >= $band) {
                    $reached += $visitors;
                }
            }
            $depths[] = [
                'depth_pct' => $band,
                'sessions_pct' => $totalVisitors > 0 ? round($reached * 100 / $totalVisitors, 2) : 0.0,
            ];
        }

        return [
            'pathname' => $query->pathname,
            'period' => [
                'from' => $query->dateRange->fromDate(),
                'to' => $query->dateRange->toDate(),
            ],
            'heatmap_type' => HeatmapType::Scroll->value,
            'total_events' => $totalVisitors,
            'click_points' => [],
            'scroll_depths' => $depths,
        ];
    }
}
