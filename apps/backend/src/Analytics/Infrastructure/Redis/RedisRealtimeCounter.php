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

namespace App\Analytics\Infrastructure\Redis;

use App\Analytics\Domain\Port\RealtimeCounterPort;
use App\Shared\Domain\ValueObject\Uuid;
use Predis\ClientInterface;
use Throwable;

/**
 * Reads the realtime visitor counter the ingestion service maintains in Redis
 * (`statflow:rt:{site_id}:visitors`, 5-minute sliding expiry — clickhouse.md §7).
 *
 * Returns null on a miss or any Redis error so the read model falls back to a
 * ClickHouse scan rather than reporting zero live visitors during an outage.
 */
final readonly class RedisRealtimeCounter implements RealtimeCounterPort
{
    private const KEY_TEMPLATE = 'statflow:rt:%s:visitors';

    public function __construct(
        private ClientInterface $redis,
    ) {
    }

    public function currentVisitors(Uuid $siteId): ?int
    {
        $key = sprintf(self::KEY_TEMPLATE, $siteId);

        try {
            /** @var int|string|null $value */
            $value = $this->redis->executeRaw(['GET', $key]);
        } catch (Throwable) {
            return null;
        }

        if ($value === null || $value === false) {
            return null;
        }

        return (int) $value;
    }
}
