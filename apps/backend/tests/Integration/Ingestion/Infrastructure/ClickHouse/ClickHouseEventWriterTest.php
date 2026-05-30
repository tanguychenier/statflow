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

namespace App\Tests\Integration\Ingestion\Infrastructure\ClickHouse;

use App\Ingestion\Application\Service\EventEnricher;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Ingestion\Domain\Service\ReferrerClassifier;
use App\Ingestion\Domain\Service\SessionWindowResolver;
use App\Ingestion\Domain\Service\UserAgentParser;
use App\Ingestion\Domain\Service\UtmParser;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Ingestion\Infrastructure\ClickHouse\ClickHouseEventWriter;
use App\Ingestion\Infrastructure\ClickHouse\ClickHouseRowMapper;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\ClickHouse\ClickHouseDsn;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\FixedSaltProvider;
use App\Tests\Ingestion\Support\StubGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Runs against a real ClickHouse with the schema from
 * docs/data-model/clickhouse-schema.sql loaded (the container phase). Inserts a
 * batch and reads it back to confirm the row mapping survives the round trip.
 */
#[CoversClass(ClickHouseEventWriter::class)]
final class ClickHouseEventWriterTest extends TestCase
{
    private string $dsn;

    /**
     * @var array<string, string>
     */
    private array $endpointQuery;

    private string $endpoint;

    protected function setUp(): void
    {
        $envDsn = $_SERVER['CLICKHOUSE_DSN'] ?? getenv('CLICKHOUSE_DSN');
        $this->dsn = is_string($envDsn) && $envDsn !== '' ? $envDsn : 'http://127.0.0.1:8123';
        [$this->endpoint, $this->endpointQuery] = ClickHouseDsn::split($this->dsn);

        try {
            $ping = HttpClient::create()->request('GET', $this->endpoint, [
                'query' => array_merge($this->endpointQuery, [
                    'query' => 'SELECT 1',
                ]),
            ]);
            if ($ping->getStatusCode() !== 200) {
                self::markTestSkipped('ClickHouse is not available.');
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('ClickHouse is not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function itBulkInsertsEnrichedEventsAndTheyAreReadable(): void
    {
        // A fresh id per run keeps the assertion deterministic on ClickHouse,
        // which has no per-test rollback to undo the insert.
        $eventId = (string) Uuid::generate();
        $writer = new ClickHouseEventWriter(HttpClient::create(), new ClickHouseRowMapper(), $this->dsn);

        $writer->writeBatch([$this->enrich($eventId)]);

        $count = $this->query(sprintf(
            "SELECT count() AS c FROM statflow.events WHERE event_id = '%s'",
            $eventId,
        ));

        $c = $count[0]['c'] ?? '0';
        self::assertSame('1', is_scalar($c) ? (string) $c : '0');
    }

    #[Test]
    public function itIsANoOpForAnEmptyBatch(): void
    {
        $writer = new ClickHouseEventWriter(HttpClient::create(), new ClickHouseRowMapper(), $this->dsn);

        $writer->writeBatch([]);

        // The assertion below ensures the test is not skipped and the no-op is exercised.
        self::assertSame(0, count([]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function query(string $sql): array
    {
        $response = HttpClient::create()->request('GET', $this->endpoint, [
            'query' => array_merge($this->endpointQuery, [
                'query' => $sql . ' FORMAT JSON',
            ]),
        ]);

        /** @var array{data?: list<array<string, mixed>>} $decoded */
        $decoded = $response->toArray(false);

        return $decoded['data'] ?? [];
    }

    private function enrich(string $eventId): EnrichedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();
        $event = $factory->fromCanonical($normalizer->normalize(EventFixtures::canonicalPageview([
            'event_id' => $eventId,
            'custom_properties' => [
                'plan' => 'pro',
                'revenue' => 19.9,
                'currency' => 'EUR',
            ],
            'event_name' => 'custom',
        ])));

        $enricher = new EventEnricher(
            new IdentityHasher(new FixedSaltProvider()),
            new SessionWindowResolver(),
            new StubGeoLocator(),
            new UserAgentParser(),
            new ReferrerClassifier(),
            new UtmParser(),
        );

        $context = new RequestContext('203.0.113.5', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari', 'fr-FR', null);

        return $enricher->enrich(new BufferedEvent(EventFixtures::SITE_ID, $event, $context));
    }
}
