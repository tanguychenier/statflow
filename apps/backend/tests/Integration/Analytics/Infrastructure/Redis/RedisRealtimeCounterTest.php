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

namespace App\Tests\Integration\Analytics\Infrastructure\Redis;

use App\Analytics\Infrastructure\Redis\RedisRealtimeCounter;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Runs against a real Redis (container phase). Confirms the counter reads the
 * key the ingestion service writes and returns null on a miss so the read model
 * can fall back to ClickHouse.
 */
#[CoversClass(RedisRealtimeCounter::class)]
final class RedisRealtimeCounterTest extends TestCase
{
    private Client $redis;

    protected function setUp(): void
    {
        $envDsn = $_SERVER['REDIS_URL'] ?? getenv('REDIS_URL');
        $dsn = is_string($envDsn) && $envDsn !== '' ? $envDsn : 'tcp://127.0.0.1:6379';

        try {
            $this->redis = new Client($dsn);
            $this->redis->connect();
        } catch (\Throwable $e) {
            self::markTestSkipped('Redis is not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function itReadsTheVisitorCounter(): void
    {
        $site = Uuid::generate();
        $key = sprintf('statflow:rt:%s:visitors', $site);
        $this->redis->set($key, '17');

        $counter = new RedisRealtimeCounter($this->redis);

        self::assertSame(17, $counter->currentVisitors($site));

        $this->redis->del([$key]);
    }

    #[Test]
    public function itReturnsNullOnAMiss(): void
    {
        $counter = new RedisRealtimeCounter($this->redis);

        self::assertNull($counter->currentVisitors(Uuid::generate()));
    }
}
