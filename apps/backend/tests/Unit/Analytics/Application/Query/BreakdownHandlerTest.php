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
use App\Analytics\Application\Query\BreakdownHandler;
use App\Analytics\Application\Query\BreakdownQuery;
use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Metric;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BreakdownHandler::class)]
#[CoversClass(BreakdownQuery::class)]
final class BreakdownHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itBreaksEventGrainMetricsDownByDimension(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'value' => '/pricing',
                'visitors' => 80,
                'pageviews' => 120,
                'sessions' => 90,
                'events' => 200,
                'converting_sessions' => 9,
                'total_rows' => 3,
            ],
            [
                'value' => '/',
                'visitors' => 60,
                'pageviews' => 70,
                'sessions' => 65,
                'events' => 100,
                'converting_sessions' => 3,
                'total_rows' => 3,
            ],
        ]]);

        $query = $this->breakdown([Metric::Visitors, Metric::ConversionRate], Dimension::Pathname, Metric::Visitors);
        $result = ($this->handler($client))($query);

        self::assertSame('pathname', $result['property']);
        self::assertSame(3, $result['total_rows']);
        self::assertSame('/pricing', $result['rows'][0]['value']);
        self::assertSame(80, $result['rows'][0]['metrics']['visitors']);
        self::assertSame(10.0, $result['rows'][0]['metrics']['conversion_rate']);
        self::assertStringContainsString('FROM statflow.events', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('LIMIT {lim', $client->selectCalls[0]['sql']);
    }

    #[Test]
    public function itQueriesSessionsForSessionScopedDimensions(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'value' => '/home',
                'visitors' => 40,
                'pageviews' => 50,
                'sessions' => 45,
                'events' => 60,
                'bounced_sessions' => 20,
                'avg_duration' => 30.0,
                'total_rows' => 1,
            ],
        ]]);

        $query = $this->breakdown([Metric::BounceRate, Metric::AvgDuration], Dimension::EntryPage, Metric::Visitors);
        $result = ($this->handler($client))($query);

        self::assertCount(1, $client->selectCalls);
        self::assertStringContainsString('FROM statflow.sessions FINAL', $client->selectCalls[0]['sql']);
        self::assertSame(44.44, $result['rows'][0]['metrics']['bounce_rate']);
        self::assertSame(30.0, $result['rows'][0]['metrics']['avg_duration']);
    }

    #[Test]
    public function eventGrainBreakdownComputesSessionMetricsFromASidecarSessionsQuery(): void
    {
        $client = new FakeClickHouseClient([
            [
                [
                    'value' => '/pricing',
                    'visitors' => 80,
                    'pageviews' => 120,
                    'sessions' => 90,
                    'events' => 200,
                    'converting_sessions' => 9,
                    'total_rows' => 2,
                ],
                [
                    'value' => '/',
                    'visitors' => 60,
                    'pageviews' => 70,
                    'sessions' => 65,
                    'events' => 100,
                    'converting_sessions' => 3,
                    'total_rows' => 2,
                ],
            ],
            [
                [
                    'value' => '/pricing',
                    'total_sessions' => 90,
                    'bounced_sessions' => 45,
                    'avg_duration' => 42.0,
                ],
            ],
        ]);

        $query = $this->breakdown([Metric::Visitors, Metric::BounceRate, Metric::AvgDuration], Dimension::Pathname, Metric::Visitors);
        $result = ($this->handler($client))($query);

        self::assertCount(2, $client->selectCalls);
        self::assertStringContainsString('FROM statflow.events', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('FROM statflow.sessions FINAL', $client->selectCalls[1]['sql']);
        self::assertStringContainsString('GROUP BY value', $client->selectCalls[1]['sql']);

        // /pricing is merged with its sessions row: 45/90 bounce, 42s duration.
        self::assertSame(50.0, $result['rows'][0]['metrics']['bounce_rate']);
        self::assertSame(42.0, $result['rows'][0]['metrics']['avg_duration']);
        // / has no sessions row: session metrics fall back to zero, not silently wrong.
        self::assertSame(0.0, $result['rows'][1]['metrics']['bounce_rate']);
        self::assertSame(0.0, $result['rows'][1]['metrics']['avg_duration']);
    }

    #[Test]
    public function eventGrainBreakdownWithoutSessionMetricsSkipsTheSessionsQuery(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'value' => '/pricing',
                'visitors' => 80,
                'pageviews' => 120,
                'sessions' => 90,
                'events' => 200,
                'converting_sessions' => 9,
                'total_rows' => 1,
            ],
        ]]);

        $query = $this->breakdown([Metric::Visitors, Metric::Pageviews], Dimension::Pathname, Metric::Visitors);
        ($this->handler($client))($query);

        self::assertCount(1, $client->selectCalls);
    }

    #[Test]
    public function generatedSqlCountsGroupedRowsWithoutDistinctInWindow(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = $this->breakdown([Metric::Visitors], Dimension::Pathname, Metric::Visitors);
        ($this->handler($client))($query);
        $sql = $client->selectCalls[0]['sql'];

        self::assertStringNotContainsStringIgnoringCase('count(DISTINCT', $sql);
        self::assertStringContainsString('WITH grouped AS (', $sql);
        self::assertStringContainsString('count() OVER () AS total_rows', $sql);
        self::assertStringContainsString('FROM grouped', $sql);
    }

    #[Test]
    public function sessionScopedDimensionSqlAlsoAvoidsDistinctInWindow(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = $this->breakdown([Metric::BounceRate], Dimension::EntryPage, Metric::BounceRate);
        ($this->handler($client))($query);
        $sql = $client->selectCalls[0]['sql'];

        self::assertStringNotContainsStringIgnoringCase('count(DISTINCT', $sql);
        self::assertStringContainsString('WITH grouped AS (', $sql);
        self::assertStringContainsString('count() OVER () AS total_rows', $sql);
        self::assertStringContainsString('ORDER BY bounced_sessions', $sql);
    }

    #[Test]
    public function itOrdersAscendingWhenRequested(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = new BreakdownQuery(
            $this->analyticsQuery([Metric::Visitors]),
            Dimension::Country,
            Metric::Pageviews,
            false,
            25,
        );
        ($this->handler($client))($query);

        self::assertStringContainsString('ORDER BY pageviews ASC', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('toString(country_code)', $client->selectCalls[0]['sql']);
    }

    #[Test]
    public function emptyResultYieldsZeroTotalRows(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $result = ($this->handler($client))($this->breakdown([Metric::Visitors], Dimension::Browser, Metric::Visitors));

        self::assertSame([], $result['rows']);
        self::assertSame(0, $result['total_rows']);
    }

    /**
     * @param list<Metric> $metrics
     */
    private function breakdown(array $metrics, Dimension $property, Metric $sortBy): BreakdownQuery
    {
        return new BreakdownQuery($this->analyticsQuery($metrics), $property, $sortBy, true, 100);
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

    private function handler(FakeClickHouseClient $client): BreakdownHandler
    {
        return new BreakdownHandler(
            $client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
        );
    }
}
