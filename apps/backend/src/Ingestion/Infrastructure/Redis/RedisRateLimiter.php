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

use App\Ingestion\Domain\Model\RateLimitDecision;
use App\Ingestion\Domain\Port\RateLimiterPort;
use Predis\ClientInterface;
use Throwable;

/**
 * Fixed-window per-site rate limiter (ADR-0009 §1) backed by a single atomic
 * Redis EVAL: increment the window counter by the cost, set the TTL on the
 * first hit, and report the remaining budget in one round trip so concurrent
 * workers cannot race past the limit.
 *
 * If Redis is unreachable the limiter fails open (allows the request): dropping
 * analytics events to a transient cache outage would be worse than briefly
 * over-admitting them.
 */
final readonly class RedisRateLimiter implements RateLimiterPort
{
    private const KEY_PREFIX = 'ratelimit:ingest:';

    /**
     * KEYS[1] window counter; ARGV[1] cost, ARGV[2] limit, ARGV[3] window seconds.
     * Returns {newCount, ttl}.
     */
    private const SCRIPT = <<<'LUA'
        local current = redis.call('INCRBY', KEYS[1], tonumber(ARGV[1]))
        if current == tonumber(ARGV[1]) then
            redis.call('EXPIRE', KEYS[1], tonumber(ARGV[3]))
        end
        local ttl = redis.call('TTL', KEYS[1])
        return {current, ttl}
        LUA;

    public function __construct(
        private ClientInterface $redis,
        private int $maxEventsPerWindow = 6000,
        private int $windowSeconds = 60,
    ) {
    }

    public function consume(string $siteId, int $cost = 1): RateLimitDecision
    {
        $key = self::KEY_PREFIX . $siteId;

        try {
            /** @var array{0: int, 1: int} $result */
            $result = $this->redis->executeRaw([
                'EVAL',
                self::SCRIPT,
                '1',
                $key,
                (string) $cost,
                (string) $this->maxEventsPerWindow,
                (string) $this->windowSeconds,
            ]);
        } catch (Throwable) {
            return RateLimitDecision::allowed();
        }

        $count = (int) ($result[0] ?? 0);
        $ttl = (int) ($result[1] ?? $this->windowSeconds);

        if ($count > $this->maxEventsPerWindow) {
            return RateLimitDecision::denied($ttl > 0 ? $ttl : $this->windowSeconds);
        }

        return RateLimitDecision::allowed();
    }
}
