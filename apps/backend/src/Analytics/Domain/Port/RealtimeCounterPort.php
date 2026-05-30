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

namespace App\Analytics\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Port (driven side) for the fast realtime visitor counter the ingestion
 * service maintains in Redis (`statflow:rt:{site_id}:visitors`, 5-minute sliding
 * window — see clickhouse.md §7).
 *
 * The realtime read model uses this for the sub-10 ms live-visitor count and
 * falls back to a ClickHouse scan (richer breakdown, or when Redis is cold).
 */
interface RealtimeCounterPort
{
    /**
     * Approximate count of unique visitors seen in the last 5 minutes, or null
     * when the counter is unavailable (cold Redis) so callers can fall back to
     * ClickHouse.
     */
    public function currentVisitors(Uuid $siteId): ?int;
}
