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

use App\Ingestion\Infrastructure\Redis\RedisRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Runs against a real Redis (the container phase). Verifies the atomic
 * fixed-window EVAL allows up to the limit and denies beyond it with a
 * Retry-After derived from the key TTL.
 */
#[CoversClass(RedisRateLimiter::class)]
final class RedisRateLimiterTest extends TestCase
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
    public function itAllowsUpToTheLimitThenDenies(): void
    {
        $limiter = new RedisRateLimiter($this->redis, maxEventsPerWindow: 3, windowSeconds: 60);
        $site = 'site-' . uniqid();

        self::assertTrue($limiter->consume($site)->allowed);
        self::assertTrue($limiter->consume($site)->allowed);
        self::assertTrue($limiter->consume($site)->allowed);

        $denied = $limiter->consume($site);
        self::assertFalse($denied->allowed);
        self::assertGreaterThanOrEqual(1, $denied->retryAfterSeconds);
    }

    #[Test]
    public function itChargesACostGreaterThanOneInASingleCall(): void
    {
        $limiter = new RedisRateLimiter($this->redis, maxEventsPerWindow: 5, windowSeconds: 60);
        $site = 'site-' . uniqid();

        self::assertTrue($limiter->consume($site, 5)->allowed);
        self::assertFalse($limiter->consume($site, 1)->allowed);
    }

    #[Test]
    public function limitsAreIsolatedPerSite(): void
    {
        $limiter = new RedisRateLimiter($this->redis, maxEventsPerWindow: 1, windowSeconds: 60);

        self::assertTrue($limiter->consume('site-a')->allowed);
        self::assertTrue($limiter->consume('site-b')->allowed);
    }
}
