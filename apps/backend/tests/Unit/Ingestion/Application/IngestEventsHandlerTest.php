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

namespace App\Tests\Unit\Ingestion\Application;

use App\Ingestion\Application\Command\IngestEventsCommand;
use App\Ingestion\Application\Dto\IngestionResult;
use App\Ingestion\Application\Handler\IngestEventsHandler;
use App\Ingestion\Domain\Exception\BufferUnavailable;
use App\Ingestion\Domain\Exception\InvalidTrackerKey;
use App\Ingestion\Domain\Exception\MalformedRequest;
use App\Ingestion\Domain\Exception\OriginNotAllowed;
use App\Ingestion\Domain\Exception\RateLimitExceeded;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\BotDetector;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\OriginAuthorizer;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\InMemoryEventBuffer;
use App\Tests\Ingestion\Support\InMemoryIdempotencyStore;
use App\Tests\Ingestion\Support\InMemoryRateLimiter;
use App\Tests\Ingestion\Support\InMemorySiteRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(IngestEventsHandler::class)]
#[CoversClass(IngestEventsCommand::class)]
final class IngestEventsHandlerTest extends TestCase
{
    private InMemorySiteRepository $sites;

    private InMemoryEventBuffer $buffer;

    private InMemoryRateLimiter $rateLimiter;

    private InMemoryIdempotencyStore $idempotency;

    private RequestContext $context;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->buffer = new InMemoryEventBuffer();
        $this->rateLimiter = new InMemoryRateLimiter();
        $this->idempotency = new InMemoryIdempotencyStore();
        $this->context = new RequestContext(
            '203.0.113.5',
            'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari',
            'fr-FR',
            'https://example.com',
        );
    }

    #[Test]
    public function itAcceptsAndBuffersAValidWireEvent(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));

        $result = $this->handle([EventFixtures::wirePageview()]);

        self::assertTrue($result->allAccepted());
        self::assertSame(1, $result->accepted());
        self::assertSame(1, $this->buffer->count());
        self::assertSame(EventFixtures::SITE_ID, $this->buffer->events[0]->siteId);
    }

    #[Test]
    public function itChargesTheRateLimiterByEventCount(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));

        $this->handle([
            EventFixtures::wirePageview([
                'eid' => '11111111-1111-4111-8111-111111111111',
            ]),
            EventFixtures::wirePageview([
                'eid' => '22222222-2222-4222-8222-222222222222',
            ]),
        ], isBatch: true);

        self::assertSame(2, $this->rateLimiter->consumed);
    }

    #[Test]
    public function itThrowsOnAnEmptyEventList(): void
    {
        $this->expectException(MalformedRequest::class);

        $this->handle([]);
    }

    #[Test]
    public function itRejectsAnUnknownKeyWith401(): void
    {
        $this->expectException(InvalidTrackerKey::class);

        $this->handle([EventFixtures::wirePageview()]);
    }

    #[Test]
    public function itRejectsAMalformedKeyOnTheFirstEvent(): void
    {
        $this->expectException(InvalidTrackerKey::class);

        $this->handle([
            EventFixtures::wirePageview([
                'k' => 'not-a-key',
            ])]);
    }

    #[Test]
    public function itEnforcesTheDomainAllowlist(): void
    {
        $this->sites->add(EventFixtures::site([
            'allowedDomains' => ['*.allowed.com'],
        ]));

        $this->expectException(OriginNotAllowed::class);

        $this->handle([EventFixtures::wirePageview()]);
    }

    #[Test]
    public function itLogsAWarningWhenAcceptingEventsForASiteWithNoAllowlist(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'allowedDomains' => [],
        ]));
        $logger = $this->spyLogger();

        $result = ($this->handler($logger))(new IngestEventsCommand([EventFixtures::wirePageview()], $this->context, false));

        self::assertTrue($result->allAccepted());
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('no origin allowlist', $logger->warnings[0]);
    }

    #[Test]
    public function itDoesNotLogTheBootstrapWarningOnceAnAllowlistMatches(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'allowedDomains' => ['example.com'],
        ]));
        $logger = $this->spyLogger();

        ($this->handler($logger))(new IngestEventsCommand([EventFixtures::wirePageview()], $this->context, false));

        self::assertSame([], $logger->warnings);
    }

    #[Test]
    public function itEnforcesTheRateLimit(): void
    {
        $this->sites->add(EventFixtures::site());
        $this->rateLimiter = new InMemoryRateLimiter(limit: 0);

        $this->expectException(RateLimitExceeded::class);

        $this->handle([EventFixtures::wirePageview()]);
    }

    #[Test]
    public function itTreatsAReplayedEventAsAcceptedButDoesNotBufferItTwice(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));

        $this->handle([EventFixtures::wirePageview()]);
        $second = $this->handle([EventFixtures::wirePageview()]);

        self::assertTrue($second->allAccepted());
        self::assertSame(1, $this->buffer->count());
    }

    #[Test]
    public function itSilentlyDropsBotTrafficButReportsItAccepted(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => true,
        ]));
        $botContext = new RequestContext('203.0.113.5', 'Googlebot/2.1', 'en', 'https://example.com');

        $result = ($this->handler())(new IngestEventsCommand([EventFixtures::wirePageview()], $botContext, false));

        self::assertTrue($result->allAccepted());
        self::assertSame(0, $this->buffer->count());
    }

    #[Test]
    public function itDoesNotFilterBotsWhenFilteringIsDisabled(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));
        $botContext = new RequestContext('203.0.113.5', 'Googlebot/2.1', 'en', 'https://example.com');

        ($this->handler())(new IngestEventsCommand([EventFixtures::wirePageview()], $botContext, false));

        self::assertSame(1, $this->buffer->count());
    }

    #[Test]
    public function itSilentlyDropsEventsFromExcludedIps(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'excludedIps' => ['203.0.113.5'],
        ]));

        $result = $this->handle([EventFixtures::wirePageview()]);

        self::assertTrue($result->allAccepted());
        self::assertSame(0, $this->buffer->count());
    }

    #[Test]
    public function itBuffersGoodEventsAndRejectsBadOnesInAMixedBatch(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));

        $bad = EventFixtures::wirePageview([
            'e' => 'not_a_real_event',
        ]);
        $good = EventFixtures::wirePageview([
            'eid' => '11111111-1111-4111-8111-111111111111',
        ]);

        $result = $this->handle([$good, $bad], isBatch: true);

        self::assertFalse($result->allAccepted());
        self::assertSame(1, $result->accepted());
        self::assertSame(1, $result->rejected());
        self::assertSame(1, $this->buffer->count());

        $rejection = $result->firstRejection();
        self::assertNotNull($rejection);
        self::assertSame(1, $rejection->index);
        self::assertSame('validation-failed', $rejection->code);
    }

    #[Test]
    public function itRejectsAnEventWhoseKeyDisagreesWithTheRequestKey(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));

        $mismatch = EventFixtures::wirePageview([
            'k' => 'stk_otherkey0000000000',
        ]);
        $good = EventFixtures::wirePageview([
            'eid' => '11111111-1111-4111-8111-111111111111',
        ]);

        // First event authenticates the request; the second carries a different key.
        $result = $this->handle([$good, $mismatch], isBatch: true);

        self::assertSame(1, $result->rejected());
        self::assertSame('invalid-tracker-key', $result->firstRejection()?->code);
    }

    #[Test]
    public function itAllowsAnyCustomEventNameWhenNoAllowlistIsConfigured(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'customEventNames' => null,
        ]));

        $result = $this->handle([
            EventFixtures::wirePageview([
                'e' => 'custom',
                'props' => [
                    'event_name' => 'anything',
                ],
            ])]);

        self::assertTrue($result->allAccepted());
        self::assertSame(1, $this->buffer->count());
    }

    #[Test]
    public function itRejectsACustomEventNotInTheAllowlist(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'customEventNames' => ['signup', 'purchase'],
        ]));

        $result = $this->handle([
            EventFixtures::wirePageview([
                'e' => 'custom',
                'props' => [
                    'event_name' => 'not_listed',
                ],
            ])]);

        self::assertSame(1, $result->rejected());
        self::assertSame('validation-failed', $result->firstRejection()?->code);
        self::assertSame(0, $this->buffer->count());
    }

    #[Test]
    public function itAcceptsACustomEventInTheAllowlist(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
            'customEventNames' => ['signup', 'purchase'],
        ]));

        $result = $this->handle([
            EventFixtures::wirePageview([
                'e' => 'custom',
                'props' => [
                    'event_name' => 'signup',
                ],
            ])]);

        self::assertTrue($result->allAccepted());
        self::assertSame(1, $this->buffer->count());
    }

    #[Test]
    public function itPropagatesABufferOutage(): void
    {
        $this->sites->add(EventFixtures::site([
            'botFilteringEnabled' => false,
        ]));
        $this->buffer->failNext();

        $this->expectException(BufferUnavailable::class);

        $this->handle([EventFixtures::wirePageview()]);
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function handle(array $events, bool $isBatch = false): IngestionResult
    {
        return ($this->handler())(new IngestEventsCommand($events, $this->context, $isBatch));
    }

    private function handler(?AbstractLogger $logger = null): IngestEventsHandler
    {
        return new IngestEventsHandler(
            $this->sites,
            $this->rateLimiter,
            $this->idempotency,
            $this->buffer,
            new WireToCanonicalNormalizer(),
            new CanonicalEventFactory(),
            new OriginAuthorizer(),
            new BotDetector(),
            $logger,
        );
    }

    /**
     * @return AbstractLogger&object{warnings: list<string>}
     */
    private function spyLogger(): AbstractLogger
    {
        return new class() extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $warnings = [];

            /**
             * @param array<array-key, mixed> $context
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) $message;
                }
            }
        };
    }
}
