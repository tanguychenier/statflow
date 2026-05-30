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

namespace App\Tests\Unit\Analytics\Application\Query;

use App\Analytics\Application\Query\AggregateMetricsHandler;
use App\Analytics\Application\Query\AggregateMetricsQueryBuilder;
use App\Analytics\Application\Query\AnalyticsQuery;
use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Metric;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateMetricsHandler::class)]
#[CoversClass(AggregateMetricsQueryBuilder::class)]
#[CoversClass(AnalyticsQuery::class)]
#[CoversClass(SegmentResolver::class)]
final class AggregateMetricsHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itComputesAllMetricsFromEventAndSessionRows(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'visitors' => 100,
                'pageviews' => 250,
                'sessions' => 120,
                'events' => 300,
                'converting_sessions' => 12,
            ]],
            [[
                'total_sessions' => 120,
                'bounced_sessions' => 48,
                'avg_duration' => 95.5,
            ]],
        ]);

        $result = ($this->handler($client))($this->query());

        self::assertSame([
            'from' => '2025-06-01',
            'to' => '2025-06-30',
        ], $result['period']);
        self::assertSame(100, $result['metrics']['visitors']);
        self::assertSame(250, $result['metrics']['pageviews']);
        self::assertSame(120, $result['metrics']['sessions']);
        self::assertSame(40.0, $result['metrics']['bounce_rate']);
        self::assertSame(95.5, $result['metrics']['avg_duration']);
        self::assertArrayNotHasKey('comparison', $result);
    }

    #[Test]
    public function itEmitsConversionRateAndEventsWhenRequested(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'visitors' => 10,
                'pageviews' => 20,
                'sessions' => 50,
                'events' => 80,
                'converting_sessions' => 5,
            ]],
            [[
                'total_sessions' => 50,
                'bounced_sessions' => 0,
                'avg_duration' => 0,
            ]],
        ]);

        $query = new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::empty(),
            null,
            null,
            [Metric::Events, Metric::ConversionRate],
        );

        $result = ($this->handler($client))($query);

        self::assertSame(80, $result['metrics']['events']);
        self::assertSame(10.0, $result['metrics']['conversion_rate']);
    }

    #[Test]
    public function itDefaultsToZeroWhenClickHouseReturnsNoRows(): void
    {
        $client = new FakeClickHouseClient([[], []]);

        $result = ($this->handler($client))($this->query());

        self::assertSame(0, $result['metrics']['visitors']);
        self::assertSame(0.0, $result['metrics']['bounce_rate']);
        self::assertSame(0.0, $result['metrics']['avg_duration']);
    }

    #[Test]
    public function itAddsAComparisonBlockAndChangePercentages(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'visitors' => 120,
                'pageviews' => 0,
                'sessions' => 120,
                'events' => 0,
                'converting_sessions' => 0,
            ]],
            [[
                'total_sessions' => 120,
                'bounced_sessions' => 0,
                'avg_duration' => 0,
            ]],
            [[
                'visitors' => 100,
                'pageviews' => 0,
                'sessions' => 100,
                'events' => 0,
                'converting_sessions' => 0,
            ]],
            [[
                'total_sessions' => 100,
                'bounced_sessions' => 0,
                'avg_duration' => 0,
            ]],
        ]);

        $query = new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::empty(),
            null,
            AnalyticsQuery::COMPARE_PREVIOUS_PERIOD,
            [Metric::Visitors],
        );

        $result = ($this->handler($client))($query);

        self::assertSame(120, $result['metrics']['visitors']);
        self::assertArrayHasKey('comparison', $result);
        /** @var array{metrics: array<string, float|int>, change_pct: array<string, float|null>, period: array{from: string, to: string}} $comparison */
        // @phpstan-ignore-next-line
        $comparison = $result['comparison'];
        self::assertSame(100, $comparison['metrics']['visitors']);
        self::assertSame(20.0, $comparison['change_pct']['visitors']);
        self::assertSame([
            'from' => '2025-05-02',
            'to' => '2025-05-31',
        ], $comparison['period']);
    }

    #[Test]
    public function changePercentageIsNullWhenPreviousValueIsZero(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'visitors' => 10,
                'sessions' => 0,
            ]],
            [[]],
            [[
                'visitors' => 0,
                'sessions' => 0,
            ]],
            [[]],
        ]);

        $query = new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-07'),
            FilterSet::empty(),
            null,
            AnalyticsQuery::COMPARE_PREVIOUS_YEAR,
            [Metric::Visitors],
        );

        $result = ($this->handler($client))($query);

        self::assertArrayHasKey('comparison', $result);
        /** @var array{metrics: array<string, float|int>, change_pct: array<string, float|null>, period: array{from: string, to: string}} $comparison */
        // @phpstan-ignore-next-line
        $comparison = $result['comparison'];
        self::assertNull($comparison['change_pct']['visitors']);
        self::assertSame([
            'from' => '2024-06-01',
            'to' => '2024-06-07',
        ], $comparison['period']);
    }

    #[Test]
    public function itPassesBoundParametersToClickHouse(): void
    {
        $client = new FakeClickHouseClient([[[]], [[]]]);

        ($this->handler($client))($this->query());

        $bindings = $client->selectCalls[0]['bindings'];
        self::assertSame((string) $this->site, $bindings['site0']);
        self::assertSame('2025-06-01 00:00:00', $bindings['start1']);
        self::assertSame('2025-07-01 00:00:00', $bindings['end2']);
        self::assertStringContainsString('FROM statflow.events', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('FROM statflow.sessions FINAL', $client->selectCalls[1]['sql']);
    }

    private function handler(FakeClickHouseClient $client): AggregateMetricsHandler
    {
        return new AggregateMetricsHandler(
            $client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
            new AggregateMetricsQueryBuilder(),
        );
    }

    private function query(): AnalyticsQuery
    {
        return new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::empty(),
            null,
            null,
            [],
        );
    }
}
