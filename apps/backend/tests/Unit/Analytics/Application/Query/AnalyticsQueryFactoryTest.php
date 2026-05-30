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
use App\Analytics\Application\Query\AnalyticsQueryFactory;
use App\Analytics\Domain\Exception\MissingQueryField;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\HeatmapType;
use App\Analytics\Domain\ValueObject\Interval;
use App\Analytics\Domain\ValueObject\Metric;
use App\Analytics\Domain\ValueObject\RetentionInterval;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnalyticsQueryFactory::class)]
#[CoversClass(MissingQueryField::class)]
final class AnalyticsQueryFactoryTest extends TestCase
{
    private Uuid $site;

    private AnalyticsQueryFactory $factory;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
        $this->factory = new AnalyticsQueryFactory();
    }

    #[Test]
    public function itBuildsAFullAnalyticsQuery(): void
    {
        $segment = Uuid::generate();
        $query = $this->factory->analyticsQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'compare_period' => 'previous_period',
            'segment_id' => (string) $segment,
            'metrics' => ['visitors', 'pageviews'],
            'filters' => [[
                'property' => 'device_type',
                'operator' => 'eq',
                'value' => 'mobile',
            ]],
            'filter_combination' => 'and',
        ]);

        self::assertSame('2025-06-01', $query->dateRange->fromDate());
        self::assertSame(AnalyticsQuery::COMPARE_PREVIOUS_PERIOD, $query->comparePeriod);
        self::assertTrue($query->segmentId?->equals($segment));
        self::assertSame([Metric::Visitors, Metric::Pageviews], $query->metrics);
        self::assertCount(1, $query->inlineFilters->filters);
    }

    #[Test]
    public function itIgnoresAnUnknownComparePeriod(): void
    {
        $query = $this->factory->analyticsQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'compare_period' => 'last_decade',
        ]);

        self::assertNull($query->comparePeriod);
        self::assertNull($query->comparisonRange());
    }

    #[Test]
    public function itRequiresDateFields(): void
    {
        $this->expectException(MissingQueryField::class);

        $this->factory->analyticsQuery($this->site, [
            'date_to' => '2025-06-30',
        ]);
    }

    #[Test]
    public function timeSeriesParsesAnExplicitInterval(): void
    {
        $query = $this->factory->timeSeriesQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'interval' => 'week',
        ]);

        self::assertSame(Interval::Week, $query->interval);
    }

    #[Test]
    public function breakdownParsesPropertySortAndLimit(): void
    {
        $query = $this->factory->breakdownQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'property' => 'country',
            'sort_by' => 'pageviews',
            'sort_order' => 'asc',
            'limit' => 5,
        ]);

        self::assertSame(Dimension::Country, $query->property);
        self::assertSame(Metric::Pageviews, $query->sortBy);
        self::assertFalse($query->sortDescending);
        self::assertSame(5, $query->limit);
    }

    #[Test]
    public function breakdownClampsTheLimitToTheMaximum(): void
    {
        $query = $this->factory->breakdownQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'property' => 'pathname',
            'limit' => 99999,
        ]);

        self::assertSame(1000, $query->limit);
    }

    #[Test]
    public function breakdownRequiresAProperty(): void
    {
        $this->expectException(MissingQueryField::class);

        $this->factory->breakdownQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
        ]);
    }

    #[Test]
    public function funnelParsesFunnelIdAndClampsWindow(): void
    {
        $funnelId = Uuid::generate();
        $query = $this->factory->funnelQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'funnel_id' => (string) $funnelId,
            'conversion_window_days' => 1000,
        ]);

        self::assertTrue($query->funnelId->equals($funnelId));
        self::assertSame(90, $query->conversionWindowDays);
    }

    #[Test]
    public function retentionDefaultsToWeeklyPageview(): void
    {
        $query = $this->factory->retentionQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
        ]);

        self::assertSame(RetentionInterval::Week, $query->interval);
        self::assertSame('pageview', $query->returnEvent);
    }

    #[Test]
    public function heatmapDefaultsToClickType(): void
    {
        $query = $this->factory->heatmapQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'pathname' => '/pricing',
            'viewport_width_min' => 1024,
        ]);

        self::assertSame(HeatmapType::Click, $query->type);
        self::assertSame('/pricing', $query->pathname);
        self::assertSame(1024, $query->viewportWidthMin);
    }

    #[Test]
    public function heatmapRequiresAPathname(): void
    {
        $this->expectException(MissingQueryField::class);

        $this->factory->heatmapQuery($this->site, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
        ]);
    }
}
