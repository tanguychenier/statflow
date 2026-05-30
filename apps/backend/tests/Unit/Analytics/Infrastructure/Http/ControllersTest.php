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

use App\Analytics\Application\Query\AggregateMetricsHandler;
use App\Analytics\Application\Query\AggregateMetricsQueryBuilder;
use App\Analytics\Application\Query\AnalyticsQueryFactory;
use App\Analytics\Application\Query\BreakdownHandler;
use App\Analytics\Application\Query\RealtimeHandler;
use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Application\Segment\CreateSegmentHandler;
use App\Analytics\Application\Segment\DeleteSegmentHandler;
use App\Analytics\Application\Segment\ListSegmentsHandler;
use App\Analytics\Application\Segment\SegmentRequestFactory;
use App\Analytics\Infrastructure\Http\Controller\AggregateMetricsController;
use App\Analytics\Infrastructure\Http\Controller\BreakdownController;
use App\Analytics\Infrastructure\Http\Controller\CreateSegmentController;
use App\Analytics\Infrastructure\Http\Controller\DeleteSegmentController;
use App\Analytics\Infrastructure\Http\Controller\ListSegmentsController;
use App\Analytics\Infrastructure\Http\Controller\RealtimeController;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\AnalyticsProblemFactoryFactory;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\FakeRealtimeCounter;
use App\Tests\Unit\Analytics\Support\FrozenClock;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AggregateMetricsController::class)]
#[CoversClass(BreakdownController::class)]
#[CoversClass(RealtimeController::class)]
#[CoversClass(ListSegmentsController::class)]
#[CoversClass(CreateSegmentController::class)]
#[CoversClass(DeleteSegmentController::class)]
#[CoversClass(AnalyticsRequestParser::class)]
final class ControllersTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function aggregateControllerReturns200WithMetrics(): void
    {
        $client = new FakeClickHouseClient([[[
            'visitors' => 5,
        ]], [[]]]);
        $controller = new AggregateMetricsController(
            new AggregateMetricsHandler($client, new PassthroughQueryCache(), $this->resolver(), new AggregateMetricsQueryBuilder()),
            new AnalyticsQueryFactory(),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $request = $this->jsonRequest([
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'metrics' => ['visitors'],
        ]);
        $response = $controller($request, (string) $this->site);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);
        /** @var array<string, mixed> $metrics */
        $metrics = $payload['metrics'];
        self::assertSame(5, $metrics['visitors']);
    }

    #[Test]
    public function aggregateControllerReturns422ForAnInvalidRange(): void
    {
        $client = new FakeClickHouseClient([]);
        $controller = new AggregateMetricsController(
            new AggregateMetricsHandler($client, new PassthroughQueryCache(), $this->resolver(), new AggregateMetricsQueryBuilder()),
            new AnalyticsQueryFactory(),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $request = $this->jsonRequest([
            'date_from' => '2025-06-30',
            'date_to' => '2025-06-01',
        ]);
        $response = $controller($request, (string) $this->site);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[Test]
    public function controllerReturns400ForInvalidJson(): void
    {
        $client = new FakeClickHouseClient([]);
        $controller = new BreakdownController(
            new BreakdownHandler($client, new PassthroughQueryCache(), $this->resolver()),
            new AnalyticsQueryFactory(),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $request = new Request(content: 'not-json');
        $response = $controller($request, (string) $this->site);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function controllerReturns400ForBadSiteId(): void
    {
        $client = new FakeClickHouseClient([]);
        $controller = new RealtimeController(
            new RealtimeHandler($client, new FakeRealtimeCounter(1)),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $response = $controller('not-a-uuid');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function realtimeControllerReturnsStats(): void
    {
        $client = new FakeClickHouseClient([[[
            'active_visitors' => 4,
        ]], [], []]);
        $controller = new RealtimeController(
            new RealtimeHandler($client, new FakeRealtimeCounter(9)),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $response = $controller((string) $this->site);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(9, $this->decode($response)['current_visitors']);
    }

    #[Test]
    public function segmentLifecycleThroughControllers(): void
    {
        $repo = new InMemorySegmentRepository();
        $cache = new PassthroughQueryCache();
        $clock = new FrozenClock(new DateTimeImmutable('2025-06-01T00:00:00Z'));

        $create = new CreateSegmentController(
            new CreateSegmentHandler($repo, $cache, $clock),
            new SegmentRequestFactory(),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );
        $createResponse = $create($this->jsonRequest([
            'name' => 'Mobile',
            'filters' => [[
                'property' => 'device_type',
                'operator' => 'eq',
                'value' => 'mobile',
            ]],
        ]), (string) $this->site);
        self::assertSame(Response::HTTP_CREATED, $createResponse->getStatusCode());
        $segmentId = $this->decode($createResponse)['id'];
        self::assertIsString($segmentId);

        $list = new ListSegmentsController(new ListSegmentsHandler($repo), new AnalyticsRequestParser(), AnalyticsProblemFactoryFactory::create());
        $listResponse = $list((string) $this->site);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        /** @var list<mixed> $listData */
        $listData = $this->decode($listResponse)['data'];
        self::assertCount(1, $listData);

        $delete = new DeleteSegmentController(new DeleteSegmentHandler($repo, $cache), new AnalyticsRequestParser(), AnalyticsProblemFactoryFactory::create());
        $deleteResponse = $delete((string) $this->site, $segmentId);
        self::assertSame(Response::HTTP_NO_CONTENT, $deleteResponse->getStatusCode());
    }

    #[Test]
    public function deletingAMissingSegmentReturns404(): void
    {
        $repo = new InMemorySegmentRepository();
        $delete = new DeleteSegmentController(
            new DeleteSegmentHandler($repo, new PassthroughQueryCache()),
            new AnalyticsRequestParser(),
            AnalyticsProblemFactoryFactory::create(),
        );

        $response = $delete((string) $this->site, (string) Uuid::generate());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function resolver(): SegmentResolver
    {
        return new SegmentResolver(new InMemorySegmentRepository());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return new Request(content: (string) json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
