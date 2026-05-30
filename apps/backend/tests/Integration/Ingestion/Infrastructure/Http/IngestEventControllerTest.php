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
use App\Ingestion\Infrastructure\Http\Controller\IngestEventController;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\InMemoryEventBuffer;
use App\Tests\Ingestion\Support\InMemorySiteRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end HTTP test for POST /api/v1/events.
 *
 * Requires the test container to bind the ingestion ports to the in-memory test
 * doubles in tests/Ingestion/Support (see the wiring notes in the context
 * report). The site repository is pre-seeded with the fixture site.
 */
#[CoversClass(IngestEventController::class)]
final class IngestEventControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // The in-memory ingestion ports keep state in the container; without this
        // a kernel reboot between requests would drop the seeded site and the
        // idempotency record.
        $this->client->disableReboot();
        $repository = self::getContainer()->get(SiteRepositoryPort::class);
        if ($repository instanceof InMemorySiteRepository) {
            $repository->add(EventFixtures::site([
                'botFilteringEnabled' => false,
            ]));
        }
    }

    #[Test]
    public function itReturns204ForAValidCanonicalEvent(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview());

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertEmpty($this->client->getResponse()->getContent());
        self::assertSame(1, $this->buffer()->count());
    }

    #[Test]
    public function itReturns204ForAValidWireEvent(): void
    {
        $this->post('/api/v1/events', EventFixtures::wirePageview());

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(1, $this->buffer()->count());
    }

    #[Test]
    public function itReturns400ForMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/events',
            server: [
                'CONTENT_TYPE' => 'text/plain',
            ],
            content: '{not json',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function itReturns401ForAnUnknownSiteKey(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview([
            'site_key' => 'stk_unknownkey00000000',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function itReturns422ForAValidationFailure(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview([
            'event_name' => 'not_a_real_event',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function everyProblemBodyCarriesTheTraceIdAndInstance(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview([
            'site_key' => 'stk_unknownkey00000000',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        /** @var array<string, mixed> $body */
        $body = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsString($body['type']);
        self::assertSame('https://statflow.io/errors/invalid-tracker-key', $body['type']);
        self::assertIsArray($body);
        self::assertArrayHasKey('trace_id', $body);
        self::assertIsString($body['trace_id']);
        self::assertNotSame('', $body['trace_id']);
        self::assertIsString($body['instance']);
        self::assertSame('/api/v1/events', $body['instance']);
    }

    #[Test]
    public function itReturns413ForAnOversizePayload(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview([
            'title' => str_repeat('a', 17 * 1024),
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    #[Test]
    public function itRejectsGetRequests(): void
    {
        $this->client->request('GET', '/api/v1/events');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    #[Test]
    public function itReturns204OnAnIdempotentReplay(): void
    {
        $this->post('/api/v1/events', EventFixtures::canonicalPageview());
        $this->post('/api/v1/events', EventFixtures::canonicalPageview());

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(1, $this->buffer()->count());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function buffer(): InMemoryEventBuffer
    {
        $buffer = self::getContainer()->get(EventBufferPort::class);
        self::assertInstanceOf(InMemoryEventBuffer::class, $buffer);

        return $buffer;
    }
}
