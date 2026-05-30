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
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Reads newly ingested events for the realtime SSE `event` channel
 * (OpenAPI `RealtimeEvent`). The SSE controller polls this with a moving
 * watermark so each event is pushed once, newest first.
 */
final readonly class RealtimeEventsHandler
{
    private const WINDOW_MINUTES = 5;

    private const MAX_ROWS = 200;

    public function __construct(
        private ClickHouseClientPort $clickHouse,
    ) {
    }

    /**
     * @return list<array<string, string>> rows ordered newest-first
     */
    public function __invoke(Uuid $siteId, ?string $sinceIso = null): array
    {
        $bindings = [
            'site' => (string) $siteId,
        ];

        // Watermark on ingested_at (server clock, monotonic) rather than the
        // client `timestamp`: the two columns diverge under client clock skew or
        // buffer delay, which would re-emit or drop events across SSE ticks.
        $watermark = '';
        if ($sinceIso !== null) {
            $bindings['since'] = $this->toClickHouseTimestamp($sinceIso);
            $watermark = 'AND ingested_at > {since:DateTime64(3)} ';
        }

        $sql = sprintf(
            'SELECT timestamp, ingested_at, event_name, pathname, referrer_source, country_code AS country, device_type, browser '
            . 'FROM statflow.events '
            . 'WHERE site_id = {site:UUID} AND timestamp >= now64(3) - INTERVAL %d MINUTE %s'
            . 'ORDER BY ingested_at DESC LIMIT %d',
            self::WINDOW_MINUTES,
            $watermark,
            self::MAX_ROWS,
        );

        $rows = $this->clickHouse->select($sql, $bindings);

        return array_map(
            static fn (array $row): array => [
                'timestamp' => self::isoTimestamp((string) ($row['timestamp'] ?? '')),
                'ingested_at' => self::isoTimestamp((string) ($row['ingested_at'] ?? '')),
                'event_name' => (string) ($row['event_name'] ?? ''),
                'pathname' => (string) ($row['pathname'] ?? ''),
                'referrer_source' => (string) ($row['referrer_source'] ?? ''),
                'country' => (string) ($row['country'] ?? ''),
                'device_type' => (string) ($row['device_type'] ?? ''),
                'browser' => (string) ($row['browser'] ?? ''),
            ],
            $rows,
        );
    }

    private function toClickHouseTimestamp(string $iso): string
    {
        return str_replace(['T', 'Z'], [' ', ''], $iso);
    }

    private static function isoTimestamp(string $clickHouseTimestamp): string
    {
        if ($clickHouseTimestamp === '') {
            return '';
        }

        return str_replace(' ', 'T', $clickHouseTimestamp) . 'Z';
    }
}
