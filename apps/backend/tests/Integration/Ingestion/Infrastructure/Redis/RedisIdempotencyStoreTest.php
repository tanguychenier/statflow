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

namespace App\Tests\Integration\Ingestion\Infrastructure\Redis;

use App\Ingestion\Infrastructure\Redis\RedisIdempotencyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Runs against a real Redis (the container phase). Verifies the atomic SET NX
 * dedup: first sight proceeds, replays are blocked, and keys are namespaced
 * per site with a 24-hour TTL.
 */
#[CoversClass(RedisIdempotencyStore::class)]
final class RedisIdempotencyStoreTest extends TestCase
{
    private Client $redis;

    protected function setUp(): void
    {
        $envUrl = getenv('REDIS_URL');
        $url = $envUrl !== false ? $envUrl : 'redis://127.0.0.1:6379';
        $this->redis = new Client($url);

        try {
            $this->redis->connect();
        } catch (\Throwable $e) {
            self::markTestSkipped('Redis is not available: ' . $e->getMessage());
        }

        $this->redis->flushdb();
    }

    protected function tearDown(): void
    {
        $this->redis->flushdb();
    }

    #[Test]
    public function itProceedsOnFirstSightAndBlocksReplays(): void
    {
        $store = new RedisIdempotencyStore($this->redis);

        self::assertTrue($store->registerIfFirstSeen('site-a', 'event-1'));
        self::assertFalse($store->registerIfFirstSeen('site-a', 'event-1'));
        self::assertFalse($store->registerIfFirstSeen('site-a', 'event-1'));
    }

    #[Test]
    public function theSameEventIdOnDifferentSitesDoesNotCollide(): void
    {
        $store = new RedisIdempotencyStore($this->redis);

        self::assertTrue($store->registerIfFirstSeen('site-a', 'shared-id'));
        self::assertTrue($store->registerIfFirstSeen('site-b', 'shared-id'));
    }

    #[Test]
    public function itSetsA24HourTtlOnTheKey(): void
    {
        $store = new RedisIdempotencyStore($this->redis);
        $store->registerIfFirstSeen('site-a', 'event-ttl');

        $ttl = (int) $this->redis->ttl('idem:event:site-a:event-ttl');

        self::assertGreaterThan(86_000, $ttl);
        self::assertLessThanOrEqual(86_400, $ttl);
    }
}
