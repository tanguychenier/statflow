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

namespace App\Ingestion\Infrastructure\Redis;

use App\Ingestion\Domain\Port\IdempotencyStorePort;
use Predis\ClientInterface;
use Throwable;

/**
 * Redis-backed 24-hour dedup store (openapi.yaml /events "Idempotency").
 *
 * `SET key 1 NX EX 86400` is atomic: it succeeds only on first sight of an
 * (site, event_id) pair, so concurrent replays cannot both proceed. The key is
 * namespaced by site so the same client-side UUID on two sites never collides.
 *
 * On a Redis error it fails open (treats the event as first-seen): the cost of
 * a rare duplicate row is far lower than silently dropping a real event.
 */
final readonly class RedisIdempotencyStore implements IdempotencyStorePort
{
    private const KEY_PREFIX = 'idem:event:';

    private const TTL_SECONDS = 86_400;

    public function __construct(
        private ClientInterface $redis,
    ) {
    }

    public function registerIfFirstSeen(string $siteId, string $eventId): bool
    {
        $key = self::KEY_PREFIX . $siteId . ':' . $eventId;

        try {
            /** @var mixed $result */
            $result = $this->redis->executeRaw([
                'SET', $key, '1', 'NX', 'EX', (string) self::TTL_SECONDS,
            ]);
        } catch (Throwable) {
            return true;
        }

        // Redis returns "OK" when the key was set, null when NX prevented it.
        return $result !== null;
    }
}
