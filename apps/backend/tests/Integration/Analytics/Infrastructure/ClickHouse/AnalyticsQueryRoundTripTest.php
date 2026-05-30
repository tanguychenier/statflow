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

namespace App\Tests\Integration\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\Query\AggregateMetricsHandler;
use App\Analytics\Application\Query\AggregateMetricsQueryBuilder;
use App\Analytics\Application\Query\AnalyticsQuery;
use App\Analytics\Application\Query\BreakdownHandler;
use App\Analytics\Application\Query\BreakdownQuery;
use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Application\Query\TimeSeriesHandler;
use App\Analytics\Application\Query\TimeSeriesQuery;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Interval;
use App\Analytics\Domain\ValueObject\Metric;
use App\Analytics\Infrastructure\ClickHouse\HttpClickHouseClient;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\ClickHouse\ClickHouseDsn;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Executes the generated query SQL against a real ClickHouse with the canonical
 * schema loaded (container phase). This is the contract test for the query
 * builders: it proves the SQL parses and returns the metrics we expect from a
 * known seed, including filters, the events/sessions split, and breakdowns.
 */
#[CoversClass(AggregateMetricsHandler::class)]
#[CoversClass(AggregateMetricsQueryBuilder::class)]
#[CoversClass(BreakdownHandler::class)]
#[CoversClass(TimeSeriesHandler::class)]
final class AnalyticsQueryRoundTripTest extends TestCase
{
    private string $dsn;

    private string $endpoint;

    /**
     * @var array<string, string>
     */
    private array $endpointQuery;

    private HttpClickHouseClient $client;

    private Uuid $site;

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

        $this->client = new HttpClickHouseClient(HttpClient::create(), $this->dsn);
        $this->site = Uuid::generate();
        $this->seed();
    }

    #[Test]
    public function aggregateMetricsMatchTheSeed(): void
    {
        $handler = new AggregateMetricsHandler(
            $this->client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
            new AggregateMetricsQueryBuilder(),
        );

        $result = ($handler)(new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::empty(),
            null,
            null,
            [Metric::Visitors, Metric::Pageviews, Metric::Sessions, Metric::Events, Metric::BounceRate, Metric::AvgDuration],
        ));

        self::assertSame(2, $result['metrics']['visitors']);
        self::assertSame(3, $result['metrics']['pageviews']);
        self::assertSame(2, $result['metrics']['sessions']);
        self::assertSame(4, $result['metrics']['events']);
        self::assertSame(50.0, $result['metrics']['bounce_rate']);
    }

    #[Test]
    public function filteredAggregateNarrowsToOneDevice(): void
    {
        $handler = new AggregateMetricsHandler(
            $this->client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
            new AggregateMetricsQueryBuilder(),
        );

        $result = ($handler)(new AnalyticsQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]),
            null,
            null,
            [Metric::Visitors],
        ));

        self::assertSame(1, $result['metrics']['visitors']);
    }

    #[Test]
    public function breakdownByPathnameReturnsTopPages(): void
    {
        $handler = new BreakdownHandler(
            $this->client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
        );

        $result = ($handler)(new BreakdownQuery(
            new AnalyticsQuery(
                $this->site,
                DateRange::fromStrings('2025-06-01', '2025-06-30'),
                FilterSet::empty(),
                null,
                null,
                [Metric::Pageviews, Metric::Visitors],
            ),
            Dimension::Pathname,
            Metric::Pageviews,
            true,
            10,
        ));

        $values = array_column($result['rows'], 'value');
        self::assertContains('/', $values);
        self::assertContains('/pricing', $values);
    }

    #[Test]
    public function timeSeriesBucketsByDay(): void
    {
        $handler = new TimeSeriesHandler(
            $this->client,
            new PassthroughQueryCache(),
            new SegmentResolver(new InMemorySegmentRepository()),
        );

        $result = ($handler)(new TimeSeriesQuery(
            new AnalyticsQuery(
                $this->site,
                DateRange::fromStrings('2025-06-01', '2025-06-30'),
                FilterSet::empty(),
                null,
                null,
                [Metric::Pageviews],
            ),
            Interval::Day,
        ));

        self::assertNotEmpty($result['series']);
    }

    private function seed(): void
    {
        $site = (string) $this->site;
        // Two visitors, two sessions. Visitor 1 (desktop) has two pageviews in one
        // session (not a bounce); visitor 2 (mobile) has one pageview (a bounce).
        $events = [
            $this->event($site, 'v1', 's1', 'desktop', '/', '2025-06-10 10:00:00.000', 'pageview', 30000),
            $this->event($site, 'v1', 's1', 'desktop', '/pricing', '2025-06-10 10:00:30.000', 'pageview', 30000),
            $this->event($site, 'v1', 's1', 'desktop', '/pricing', '2025-06-10 10:00:35.000', 'engagement', 5000),
            $this->event($site, 'v2', 's2', 'mobile', '/', '2025-06-11 09:00:00.000', 'pageview', 0),
        ];
        $this->client->insert('statflow.events', $events);

        $sessions = [
            $this->session($site, 's1', 'v1', 'desktop', '/', '/pricing', '2025-06-10 10:00:00.000', '2025-06-10 10:00:35.000', 35, 2, 0),
            $this->session($site, 's2', 'v2', 'mobile', '/', '/', '2025-06-11 09:00:00.000', '2025-06-11 09:00:00.000', 0, 1, 1),
        ];
        $this->client->insert('statflow.sessions', $sessions);

        // Force the parts to merge so sessions FINAL is deterministic for the test.
        HttpClient::create()->request('POST', $this->endpoint, [
            'query' => array_merge($this->endpointQuery, [
                'query' => 'OPTIMIZE TABLE statflow.sessions FINAL',
            ]),
            'headers' => [
                'Content-Type' => 'text/plain',
            ],
            'body' => '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        string $site,
        string $visitor,
        string $session,
        string $device,
        string $pathname,
        string $timestamp,
        string $eventName,
        int $engagementMs,
    ): array {
        return [
            'site_id' => $site,
            'event_id' => (string) Uuid::generate(),
            'event_name' => $eventName,
            'timestamp' => $timestamp,
            'seq' => 1,
            'tracker_version' => '1.0.0',
            'visitor_id' => $visitor,
            'session_id' => $session,
            'hostname' => 'example.com',
            'pathname' => $pathname,
            'referrer' => '',
            'referrer_source' => 'direct',
            'device_type' => $device,
            'country_code' => 'FR',
            'engagement_time_ms' => $engagementMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function session(
        string $site,
        string $session,
        string $visitor,
        string $device,
        string $entry,
        string $exit,
        string $start,
        string $end,
        int $duration,
        int $pageviews,
        int $bounce,
    ): array {
        return [
            'site_id' => $site,
            'session_id' => $session,
            'visitor_id' => $visitor,
            'started_at' => $start,
            'ended_at' => $end,
            'duration_s' => $duration,
            'entry_page' => $entry,
            'exit_page' => $exit,
            'referrer' => '',
            'referrer_source' => 'direct',
            'country_code' => 'FR',
            'device_type' => $device,
            'pageview_count' => $pageviews,
            'event_count' => $pageviews,
            'bounce' => $bounce,
            'total_engagement_ms' => 0,
            'version' => $end,
        ];
    }
}
