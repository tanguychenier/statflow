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

namespace App\Tests\Unit\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\UnknownDimension;
use App\Analytics\Domain\Exception\UnknownInterval;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\HeatmapType;
use App\Analytics\Domain\ValueObject\RetentionInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeatmapType::class)]
#[CoversClass(RetentionInterval::class)]
#[CoversClass(FilterOperator::class)]
final class EnumParsingTest extends TestCase
{
    #[Test]
    public function heatmapTypeParses(): void
    {
        self::assertSame(HeatmapType::Click, HeatmapType::fromString('click'));
        self::assertSame(HeatmapType::Scroll, HeatmapType::fromString('scroll'));
    }

    #[Test]
    public function heatmapTypeRejectsUnknown(): void
    {
        $this->expectException(UnknownDimension::class);

        HeatmapType::fromString('rage');
    }

    #[Test]
    public function retentionIntervalParsesAndMapsToClickHouse(): void
    {
        self::assertSame('toStartOfWeek', RetentionInterval::Week->clickHouseBucketFunction());
        self::assertSame('WEEK', RetentionInterval::Week->intervalUnit());
        self::assertSame('toStartOfMonth', RetentionInterval::Month->clickHouseBucketFunction());
        self::assertSame('MONTH', RetentionInterval::Month->intervalUnit());
    }

    #[Test]
    public function retentionIntervalRejectsUnknown(): void
    {
        $this->expectException(UnknownInterval::class);

        RetentionInterval::fromString('daily');
    }

    #[Test]
    public function filterOperatorListArity(): void
    {
        self::assertTrue(FilterOperator::In->expectsList());
        self::assertTrue(FilterOperator::NotIn->expectsList());
        self::assertFalse(FilterOperator::Eq->expectsList());
    }
}
