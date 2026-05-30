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
use App\Analytics\Domain\Port\RealtimeCounterPort;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Builds the realtime stats payload for the last 5 minutes
 * (OpenAPI `getRealtimeStats` and the SSE `stats` channel).
 *
 * The live visitor count comes from the Redis sliding counter the ingestion
 * service maintains; when Redis is cold the ClickHouse scan's own visitor count
 * is used. Top pages / sources always come from ClickHouse (richer breakdown,
 * cheap over a 5-minute window — clickhouse.md §7).
 */
final readonly class RealtimeHandler
{
    private const WINDOW_MINUTES = 5;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
        private RealtimeCounterPort $counter,
    ) {
    }

    /**
     * @return array{
     *   current_visitors: int,
     *   top_pages: list<array{pathname: string, visitors: int}>,
     *   top_sources: list<array{referrer_domain: string, visitors: int}>,
     *   updated_at: string
     * }
     */
    public function __invoke(Uuid $siteId): array
    {
        $stats = $this->scan($siteId);
        $redisVisitors = $this->counter->currentVisitors($siteId);

        return [
            'current_visitors' => $redisVisitors ?? (int) ($stats['active_visitors'] ?? 0),
            'top_pages' => $this->topPages($siteId),
            'top_sources' => $this->topSources($siteId),
            'updated_at' => $this->now(),
        ];
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function scan(Uuid $siteId): array
    {
        $sql = sprintf(
            'SELECT uniq(visitor_id) AS active_visitors, uniq(session_id) AS active_sessions '
            . 'FROM statflow.events '
            . 'WHERE site_id = {site:UUID} AND timestamp >= now64(3) - INTERVAL %d MINUTE',
            self::WINDOW_MINUTES,
        );

        return $this->clickHouse->select($sql, [
            'site' => (string) $siteId,
        ])[0] ?? [];
    }

    /**
     * @return list<array{pathname: string, visitors: int}>
     */
    private function topPages(Uuid $siteId): array
    {
        $sql = sprintf(
            'SELECT pathname, uniq(visitor_id) AS visitors '
            . 'FROM statflow.events '
            . "WHERE site_id = {site:UUID} AND timestamp >= now64(3) - INTERVAL %d MINUTE AND event_name IN ('pageview', 'route_change') "
            . 'GROUP BY pathname ORDER BY visitors DESC LIMIT 10',
            self::WINDOW_MINUTES,
        );

        $rows = $this->clickHouse->select($sql, [
            'site' => (string) $siteId,
        ]);

        return array_map(
            static fn (array $row): array => [
                'pathname' => (string) ($row['pathname'] ?? ''),
                'visitors' => (int) ($row['visitors'] ?? 0),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{referrer_domain: string, visitors: int}>
     */
    private function topSources(Uuid $siteId): array
    {
        $sql = sprintf(
            'SELECT referrer_source AS referrer_domain, uniq(visitor_id) AS visitors '
            . 'FROM statflow.events '
            . 'WHERE site_id = {site:UUID} AND timestamp >= now64(3) - INTERVAL %d MINUTE '
            . 'GROUP BY referrer_source ORDER BY visitors DESC LIMIT 10',
            self::WINDOW_MINUTES,
        );

        $rows = $this->clickHouse->select($sql, [
            'site' => (string) $siteId,
        ]);

        return array_map(
            static fn (array $row): array => [
                'referrer_domain' => (string) ($row['referrer_domain'] ?? ''),
                'visitors' => (int) ($row['visitors'] ?? 0),
            ],
            $rows,
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
