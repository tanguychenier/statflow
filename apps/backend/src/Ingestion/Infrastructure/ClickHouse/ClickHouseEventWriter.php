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

namespace App\Ingestion\Infrastructure\ClickHouse;

use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Port\EventWriterPort;
use App\Shared\Infrastructure\ClickHouse\ClickHouseDsn;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bulk-inserts enriched events into the ClickHouse `events` table over the HTTP
 * interface using JSONEachRow — one request per batch, never per row
 * (architecture.md §"Batch Writer Worker").
 *
 * The Ingestion writer talks to ClickHouse directly rather than borrowing the
 * Analytics client so the two contexts stay decoupled (Deptrac); the wire
 * format (JSONEachRow over the HTTP endpoint) is the same.
 */
final readonly class ClickHouseEventWriter implements EventWriterPort
{
    private const TABLE = 'statflow.events';

    private string $endpoint;

    /**
     * @var array<string, string>
     */
    private array $endpointQuery;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ClickHouseRowMapper $rowMapper,
        string $dsn,
    ) {
        [$this->endpoint, $this->endpointQuery] = ClickHouseDsn::split($dsn);
    }

    public function writeBatch(array $events): void
    {
        if ($events === []) {
            return;
        }

        // JSON_FORCE_OBJECT: an empty `properties` map must serialise as `{}`,
        // not `[]` — ClickHouse's JSONEachRow parser rejects a JSON array for a
        // Map(String,String) column. Every value in the row is a scalar or an
        // associative map, so forcing objects is safe and never mangles a list.
        $ndjson = implode("\n", array_map(
            fn (EnrichedEvent $event): string => json_encode(
                $this->rowMapper->toRow($event),
                JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT,
            ),
            $events,
        ));

        $response = $this->httpClient->request('POST', $this->endpoint, [
            'query' => array_merge($this->endpointQuery, [
                'query' => sprintf('INSERT INTO %s FORMAT JSONEachRow', self::TABLE),
            ]),
            'headers' => [
                'Content-Type' => 'application/x-ndjson',
            ],
            'body' => $ndjson,
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new RuntimeException(sprintf(
                'ClickHouse insert failed (HTTP %d): %s',
                $status,
                $response->getContent(false),
            ));
        }
    }
}
