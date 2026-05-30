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

namespace App\Tests\Integration\Ingestion\Infrastructure\Http;

use App\Ingestion\Domain\Port\EventBufferPort;
use App\Ingestion\Domain\Port\SiteRepositoryPort;
use App\Ingestion\Infrastructure\Http\Controller\IngestEventBatchController;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\InMemoryEventBuffer;
use App\Tests\Ingestion\Support\InMemorySiteRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end HTTP test for POST /api/v1/events/batch (openapi.yaml
 * `ingestEventBatch`). 204 when all accepted, 200 with a per-item body on
 * partial success.
 */
#[CoversClass(IngestEventBatchController::class)]
final class IngestEventBatchControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $repository = self::getContainer()->get(SiteRepositoryPort::class);
        if ($repository instanceof InMemorySiteRepository) {
            $repository->add(EventFixtures::site([
                'botFilteringEnabled' => false,
            ]));
        }
    }

    #[Test]
    public function itReturns204WhenAllEventsAreAccepted(): void
    {
        $this->postBatch([
            EventFixtures::wirePageview([
                'eid' => '11111111-1111-4111-8111-111111111111',
            ]),
            EventFixtures::wirePageview([
                'eid' => '22222222-2222-4222-8222-222222222222',
            ]),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(2, $this->buffer()->count());
    }

    #[Test]
    public function itReturns200WithPerItemResultsOnPartialSuccess(): void
    {
        $this->postBatch([
            EventFixtures::wirePageview([
                'eid' => '11111111-1111-4111-8111-111111111111',
            ]),
            EventFixtures::wirePageview([
                'eid' => '22222222-2222-4222-8222-222222222222',
                'e' => 'not_real',
            ]),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['accepted']);
        self::assertSame(1, $body['rejected']);
        /** @var list<array<string, mixed>> $results */
        $results = $body['results'];
        self::assertSame('accepted', $results[0]['status']);
        self::assertSame('rejected', $results[1]['status']);
        self::assertSame('validation-failed', $results[1]['code']);
        self::assertSame(1, $this->buffer()->count());
    }

    #[Test]
    public function itReturns413WhenTheBatchExceeds100Events(): void
    {
        $events = [];
        for ($i = 0; $i < 101; ++$i) {
            $events[] = EventFixtures::wirePageview([
                'eid' => sprintf('%08d-1111-4111-8111-111111111111', $i),
            ]);
        }

        $this->postBatch($events);

        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function postBatch(array $events): void
    {
        $this->client->request(
            'POST',
            '/api/v1/events/batch',
            server: [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
            content: json_encode([
                'events' => $events,
                'sdk' => 'tracker',
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function buffer(): InMemoryEventBuffer
    {
        $buffer = self::getContainer()->get(EventBufferPort::class);
        self::assertInstanceOf(InMemoryEventBuffer::class, $buffer);

        return $buffer;
    }
}
