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

namespace App\Tests\Unit\Analytics\Infrastructure\Http;

use App\Analytics\Application\Query\RealtimeEventsHandler;
use App\Analytics\Application\Query\RealtimeHandler;
use App\Analytics\Infrastructure\Http\Controller\RealtimeStreamController;
use App\Analytics\Infrastructure\Http\Sse\SseWriter;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\AnalyticsProblemFactoryFactory;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\FakeRealtimeCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[CoversClass(RealtimeStreamController::class)]
final class RealtimeStreamControllerTest extends TestCase
{
    #[Test]
    public function itStreamsStatsAndEventsAsServerSentEvents(): void
    {
        $writer = $this->capturingWriter();
        $client = new FakeClickHouseClient($this->seedResults());
        $controller = new RealtimeStreamController(
            new RealtimeHandler($client, new FakeRealtimeCounter(3)),
            new RealtimeEventsHandler($client),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
            $writer,
        );

        $response = $controller(((string) Uuid::generate()));

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('text/event-stream', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $response->sendContent();

        /** @var list<array{0: string, 1: array<string, mixed>}> $emitted */
        $emitted = $writer->emitted; // @phpstan-ignore-line (property added by anonymous class)
        $statsEmitted = array_filter($emitted, static fn (array $e): bool => $e[0] === 'stats');
        self::assertNotEmpty($statsEmitted);
    }

    #[Test]
    public function itReturnsAProblemForAnInvalidSiteId(): void
    {
        $writer = $this->capturingWriter();
        $client = new FakeClickHouseClient();
        $controller = new RealtimeStreamController(
            new RealtimeHandler($client, new FakeRealtimeCounter(null)),
            new RealtimeEventsHandler($client),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
            $writer,
        );

        $response = $controller('not-a-uuid');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @return list<list<array<string, int|float|string|null>>>
     */
    private function seedResults(): array
    {
        $results = [];
        // Each tick: realtime scan (1) + top pages (1) + top sources (1) + events (1).
        for ($i = 0; $i < 30; ++$i) {
            $results[] = [[
                'active_visitors' => 1,
            ]];
            $results[] = [];
            $results[] = [];
            $results[] = [];
        }

        return $results;
    }

    private function capturingWriter(): SseWriter
    {
        return new class() extends SseWriter {
            /**
             * @var list<array{0: string, 1: array<string, mixed>}>
             */
            public array $emitted = [];

            public function emit(string $event, array $data): void
            {
                $this->emitted[] = [$event, $data];
            }

            protected function write(string $payload): void
            {
            }

            public function flush(): void
            {
            }

            public function sleep(int $seconds): void
            {
            }
        };
    }
}
