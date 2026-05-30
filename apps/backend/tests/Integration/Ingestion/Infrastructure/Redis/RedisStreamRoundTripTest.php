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

use App\Ingestion\Application\Service\EventBatchWriter;
use App\Ingestion\Application\Service\EventEnricher;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Ingestion\Domain\Service\ReferrerClassifier;
use App\Ingestion\Domain\Service\SessionWindowResolver;
use App\Ingestion\Domain\Service\UserAgentParser;
use App\Ingestion\Domain\Service\UtmParser;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Ingestion\Infrastructure\Redis\BufferedEventCodec;
use App\Ingestion\Infrastructure\Redis\RedisStreamConsumer;
use App\Ingestion\Infrastructure\Redis\RedisStreamEventBuffer;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\FixedSaltProvider;
use App\Tests\Ingestion\Support\InMemoryEventWriter;
use App\Tests\Ingestion\Support\InMemorySiteRepository;
use App\Tests\Ingestion\Support\StubGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Full buffer → consumer round trip against a real Redis (the container phase):
 * append events with XADD, read them back through the consumer group, enrich,
 * write, and acknowledge. Confirms the pending-entries list is drained on ack.
 */
#[CoversClass(RedisStreamEventBuffer::class)]
#[CoversClass(RedisStreamConsumer::class)]
final class RedisStreamRoundTripTest extends TestCase
{
    private Client $redis;

    private BufferedEventCodec $codec;

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
        $this->codec = new BufferedEventCodec();
    }

    protected function tearDown(): void
    {
        $this->redis->flushdb();
    }

    #[Test]
    public function itBuffersAndConsumesEvents(): void
    {
        $buffer = new RedisStreamEventBuffer($this->redis, $this->codec);
        $buffer->append([
            $this->buffered('11111111-1111-4111-8111-111111111111'),
            $this->buffered('22222222-2222-4222-8222-222222222222'),
        ]);

        $writer = new InMemoryEventWriter();
        $consumer = new RedisStreamConsumer($this->redis, $this->codec, $this->batchWriter($writer));
        $consumer->ensureGroup();

        // ensureGroup uses '$' as start id, so it only sees events appended after
        // group creation; re-append to land entries the group can read.
        $buffer->append([$this->buffered('33333333-3333-4333-8333-333333333333')]);

        $written = $consumer->consumeOnce('worker-1', batchSize: 100, blockMs: 100);

        self::assertSame(1, $written);
        self::assertCount(1, $writer->allEvents());

        $pending = $this->redis->executeRaw(['XPENDING', 'events:raw', 'statflow_writers']);
        $pendingCount = is_array($pending) && isset($pending[0]) && is_numeric($pending[0]) ? (int) $pending[0] : 0;
        self::assertSame(0, $pendingCount);
    }

    #[Test]
    public function ensureGroupIsIdempotent(): void
    {
        $consumer = new RedisStreamConsumer($this->redis, $this->codec, $this->batchWriter(new InMemoryEventWriter()));

        $consumer->ensureGroup();
        $consumer->ensureGroup();

        // Calling ensureGroup twice must not throw; reaching here is the assertion.
        self::assertSame(0, 0);
    }

    private function buffered(string $eventId): BufferedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();
        $event = $factory->fromCanonical($normalizer->normalize(
            EventFixtures::canonicalPageview([
                'event_id' => $eventId,
            ]),
        ));
        $context = new RequestContext('203.0.113.5', 'Mozilla/5.0 Chrome/120 Safari', 'fr', null);

        return new BufferedEvent(EventFixtures::SITE_ID, $event, $context);
    }

    private function batchWriter(InMemoryEventWriter $writer): EventBatchWriter
    {
        $sites = new InMemorySiteRepository();
        $sites->add(EventFixtures::site());

        $enricher = new EventEnricher(
            new IdentityHasher(new FixedSaltProvider()),
            new SessionWindowResolver(),
            new StubGeoLocator(),
            new UserAgentParser(),
            new ReferrerClassifier(),
            new UtmParser(),
        );

        return new EventBatchWriter($enricher, $writer, $sites);
    }
}
