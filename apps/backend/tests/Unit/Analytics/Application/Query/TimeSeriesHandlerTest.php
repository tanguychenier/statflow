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

use App\Analytics\Application\Query\AnalyticsQuery;
use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Application\Query\TimeSeriesHandler;
use App\Analytics\Application\Query\TimeSeriesQuery;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Interval;
use App\Analytics\Domain\ValueObject\Metric;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeSeriesHandler::class)]
#[CoversClass(TimeSeriesQuery::class)]
final class TimeSeriesHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itReturnsEventGrainBucketsWithoutQueryingSessions(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'bucket' => '2025-06-01 00:00:00',
                'visitors' => 10,
                'pageviews' => 20,
                'sessions' => 12,
                'events' => 30,
                'converting_sessions' => 1,
            ],
            [
                'bucket' => '2025-06-02 00:00:00',
                'visitors' => 15,
                'pageviews' => 25,
                'sessions' => 16,
                'events' => 40,
                'converting_sessions' => 2,
            ],
        ]]);

        $query = new TimeSeriesQuery($this->analyticsQuery([Metric::Visitors, Metric::Pageviews]), Interval::Day);
        $result = ($this->handler($client))($query);

        self::assertSame('day', $result['interval']);
        self::assertCount(2, $result['series']);
        self::assertSame('2025-06-01T00:00:00Z', $result['series'][0]['bucket']);
        self::assertSame([
            'visitors' => 10,
            'pageviews' => 20,
        ], $result['series'][0]['metrics']);
        self::assertCount(1, $client->selectCalls);
    }

    #[Test]
    public function itMergesSessionBucketsWhenASessionMetricIsRequested(): void
    {
        $client = new FakeClickHouseClient([
            [
                [
                    'bucket' => '2025-06-01 00:00:00',
                    'visitors' => 10,
                    'sessions' => 12,
                    'pageviews' => 0,
                    'events' => 0,
                    'converting_sessions' => 0,
                ],
                [
                    'bucket' => '2025-06-02 00:00:00',
                    'visitors' => 15,
                    'sessions' => 16,
                    'pageviews' => 0,
                    'events' => 0,
                    'converting_sessions' => 0,
                ],
            ],
            [
                [
                    'bucket' => '2025-06-01 00:00:00',
                    'total_sessions' => 12,
                    'bounced_sessions' => 6,
                    'avg_duration' => 50.0,
                ],
            ],
        ]);

        $query = new TimeSeriesQuery($this->analyticsQuery([Metric::Visitors, Metric::BounceRate]), Interval::Day);
        $result = ($this->handler($client))($query);

        self::assertCount(2, $client->selectCalls);
        self::assertSame(50.0, $result['series'][0]['metrics']['bounce_rate']);
        self::assertSame(0.0, $result['series'][1]['metrics']['bounce_rate']);
    }

    #[Test]
    public function itMergesTheFullUnionOfBucketsAcrossGrains(): void
    {
        // The session grain carries 06-03 which the event grain never produced;
        // the merge must keep it rather than dropping it.
        $client = new FakeClickHouseClient([
            [
                [
                    'bucket' => '2025-06-01 00:00:00',
                    'visitors' => 10,
                    'sessions' => 12,
                    'pageviews' => 0,
                    'events' => 0,
                    'converting_sessions' => 0,
                ],
                [
                    'bucket' => '2025-06-02 00:00:00',
                    'visitors' => 15,
                    'sessions' => 16,
                    'pageviews' => 0,
                    'events' => 0,
                    'converting_sessions' => 0,
                ],
            ],
            [
                [
                    'bucket' => '2025-06-02 00:00:00',
                    'total_sessions' => 16,
                    'bounced_sessions' => 4,
                    'avg_duration' => 70.0,
                ],
                [
                    'bucket' => '2025-06-03 00:00:00',
                    'total_sessions' => 8,
                    'bounced_sessions' => 2,
                    'avg_duration' => 30.0,
                ],
            ],
        ]);

        $query = new TimeSeriesQuery($this->analyticsQuery([Metric::Visitors, Metric::BounceRate]), Interval::Day);
        $result = ($this->handler($client))($query);

        self::assertCount(3, $result['series']);
        self::assertSame('2025-06-01T00:00:00Z', $result['series'][0]['bucket']);
        self::assertSame('2025-06-03T00:00:00Z', $result['series'][2]['bucket']);
        // Session-only bucket keeps its session metric and zeroes the event one.
        self::assertSame(25.0, $result['series'][2]['metrics']['bounce_rate']);
        self::assertSame(0, $result['series'][2]['metrics']['visitors']);
    }

    #[Test]
    public function itAutoSelectsTheIntervalWhenOmitted(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = new TimeSeriesQuery($this->analyticsQuery([Metric::Visitors]), null);
        $result = ($this->handler($client))($query);

        self::assertSame('day', $result['interval']);
        self::assertStringContainsString('toStartOfDay(timestamp)', $client->selectCalls[0]['sql']);
    }

    /**
     * @param list<Metric> $metrics
     */
    private function analyticsQuery(array $metrics): AnalyticsQuery
    {
        return new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::empty(),
            null,
            null,
            $metrics,
        );
    }

    private function handler(FakeClickHouseClient $client): TimeSeriesHandler
    {
        return new TimeSeriesHandler(
            $client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
        );
    }
}
