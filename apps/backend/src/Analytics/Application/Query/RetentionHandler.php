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

/**
 * Computes a cohort retention table (OpenAPI `queryRetention`).
 *
 * Each visitor's acquisition period is their first activity bucket; retention
 * for offset N is the share of that cohort active again N periods later. The
 * query is computed live from `events` so it honours an arbitrary return-event
 * filter, rather than the nightly `retention_cohorts` rollup which is fixed to
 * weekly/pageview cohorts.
 */
final readonly class RetentionHandler
{
    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private QueryCachePort $cache,
    ) {
    }

    /**
     * @return array{
     *   interval: string,
     *   cohorts: list<array<string, mixed>>
     * }
     */
    public function __invoke(RetentionQuery $query): array
    {
        $clause = $this->statement($query);
        $key = QueryFingerprint::fromClauses('retention:' . $query->interval->value, [$clause]);

        /** @var list<array<string, mixed>> $cohorts */
        $cohorts = $this->cache->get($query->siteId, $key, self::CACHE_TTL_SECONDS, function () use ($clause): array {
            $rows = $this->clickHouse->select($clause->sql, $clause->bindings);

            return $this->assembleCohorts($rows);
        });

        return [
            'interval' => $query->interval->value,
            'cohorts' => $cohorts,
        ];
    }

    private function statement(RetentionQuery $query): SqlClause
    {
        $bucket = $query->interval->clickHouseBucketFunction();
        $unit = $query->interval->intervalUnit();

        $bindings = [
            'site' => (string) $query->siteId,
            'start' => $query->dateRange->fromDate() . ' 00:00:00',
            'end' => $query->dateRange->exclusiveEnd()->format('Y-m-d') . ' 00:00:00',
            'return_event' => $query->returnEvent,
        ];

        // first_seen: each visitor's acquisition bucket (computed over the whole
        // window). activity: every bucket a visitor was active in for the chosen
        // return event. cohort_sizes: the acquisition cohort denominator, the
        // distinct visitors per acquisition bucket, computed from first_seen
        // alone so it is never narrowed by the return-event filter. The offset is
        // the integer period distance between acquisition and activity.
        $sql = sprintf(
            'WITH '
            . 'first_seen AS ('
            . '  SELECT visitor_id, %1$s(min(timestamp)) AS cohort_bucket '
            . '  FROM statflow.events '
            . '  WHERE site_id = {site:UUID} AND timestamp >= {start:DateTime64(3)} AND timestamp < {end:DateTime64(3)} '
            . '  GROUP BY visitor_id'
            . '), '
            . 'activity AS ('
            . '  SELECT DISTINCT visitor_id, %1$s(timestamp) AS active_bucket '
            . '  FROM statflow.events '
            . '  WHERE site_id = {site:UUID} AND timestamp >= {start:DateTime64(3)} AND timestamp < {end:DateTime64(3)} '
            . '    AND event_name = {return_event:String}'
            . '), '
            . 'cohort_sizes AS ('
            . '  SELECT cohort_bucket, uniq(visitor_id) AS cohort_size '
            . '  FROM first_seen '
            . '  GROUP BY cohort_bucket'
            . ') '
            . 'SELECT '
            . '  f.cohort_bucket AS cohort_date, '
            . '  c.cohort_size AS cohort_size, '
            . '  dateDiff(\'%2$s\', f.cohort_bucket, a.active_bucket) AS period, '
            . '  uniq(f.visitor_id) AS retained_count '
            . 'FROM first_seen AS f '
            . 'INNER JOIN activity AS a ON f.visitor_id = a.visitor_id '
            . 'INNER JOIN cohort_sizes AS c ON f.cohort_bucket = c.cohort_bucket '
            . 'WHERE a.active_bucket >= f.cohort_bucket '
            . 'GROUP BY cohort_date, cohort_size, period '
            . 'ORDER BY cohort_date, period',
            $bucket,
            strtolower($unit),
        );

        return new SqlClause($sql, $bindings);
    }

    /**
     * @param list<array<string, int|float|string|null>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function assembleCohorts(array $rows): array
    {
        /** @var array<string, array{cohort_date: string, cohort_size: int, periods: array<int, int>}> $cohorts */
        $cohorts = [];
        foreach ($rows as $row) {
            $date = $this->dateOnly((string) ($row['cohort_date'] ?? ''));
            $period = (int) ($row['period'] ?? 0);
            $count = (int) ($row['retained_count'] ?? 0);

            if (!isset($cohorts[$date])) {
                $cohorts[$date] = [
                    'cohort_date' => $date,
                    'cohort_size' => 0,
                    'periods' => [],
                ];
            }
            $cohorts[$date]['periods'][$period] = $count;
            // cohort_size is the acquisition cohort denominator computed from
            // first_seen, independent of the return-event join; it is the same on
            // every row for a cohort, so the last write is authoritative.
            $cohorts[$date]['cohort_size'] = (int) ($row['cohort_size'] ?? $cohorts[$date]['cohort_size']);
        }

        $out = [];
        foreach ($cohorts as $cohort) {
            $size = $cohort['cohort_size'];
            $maxPeriod = $cohort['periods'] === [] ? 0 : max(array_keys($cohort['periods']));

            $retention = [];
            for ($period = 0; $period <= $maxPeriod; ++$period) {
                $retained = $cohort['periods'][$period] ?? 0;
                $retention[] = [
                    'period' => $period,
                    'retained_count' => $retained,
                    'retention_pct' => $size > 0 ? round($retained * 100 / $size, 2) : 0.0,
                ];
            }

            $out[] = [
                'cohort_date' => $cohort['cohort_date'],
                'cohort_size' => $size,
                'retention' => $retention,
            ];
        }

        return $out;
    }

    private function dateOnly(string $clickHouseTimestamp): string
    {
        return substr($clickHouseTimestamp, 0, 10);
    }
}
