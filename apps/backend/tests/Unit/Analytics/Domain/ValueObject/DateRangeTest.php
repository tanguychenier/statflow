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

use App\Analytics\Domain\Exception\InvalidDateRange;
use App\Analytics\Domain\ValueObject\DateRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateRange::class)]
#[CoversClass(InvalidDateRange::class)]
final class DateRangeTest extends TestCase
{
    #[Test]
    public function itParsesAnInclusiveRange(): void
    {
        $range = DateRange::fromStrings('2025-06-01', '2025-06-30');

        self::assertSame('2025-06-01', $range->fromDate());
        self::assertSame('2025-06-30', $range->toDate());
        self::assertSame(30, $range->inclusiveDayCount());
        self::assertSame('2025-07-01', $range->exclusiveEnd()->format('Y-m-d'));
    }

    #[Test]
    public function itAllowsASingleDayRange(): void
    {
        $range = DateRange::fromStrings('2025-06-15', '2025-06-15');

        self::assertSame(1, $range->inclusiveDayCount());
        self::assertSame('2025-06-16', $range->exclusiveEnd()->format('Y-m-d'));
    }

    #[Test]
    public function itComputesThePreviousPeriod(): void
    {
        $previous = DateRange::fromStrings('2025-06-01', '2025-06-30')->previousPeriod();

        self::assertSame('2025-05-02', $previous->fromDate());
        self::assertSame('2025-05-31', $previous->toDate());
        self::assertSame(30, $previous->inclusiveDayCount());
    }

    #[Test]
    public function itComputesThePreviousYear(): void
    {
        $previous = DateRange::fromStrings('2025-06-01', '2025-06-30')->previousYear();

        self::assertSame('2024-06-01', $previous->fromDate());
        self::assertSame('2024-06-30', $previous->toDate());
    }

    #[Test]
    public function itRejectsAnInvertedRange(): void
    {
        $this->expectException(InvalidDateRange::class);

        DateRange::fromStrings('2025-06-30', '2025-06-01');
    }

    #[Test]
    public function itRejectsAMalformedDate(): void
    {
        $this->expectException(InvalidDateRange::class);

        DateRange::fromStrings('2025-13-40', '2025-06-01');
    }

    #[Test]
    public function itRejectsANonDateString(): void
    {
        $this->expectException(InvalidDateRange::class);

        DateRange::fromStrings('yesterday', '2025-06-01');
    }

    #[Test]
    public function itRejectsARangeLongerThanTheMaximum(): void
    {
        $this->expectException(InvalidDateRange::class);

        DateRange::fromStrings('2020-01-01', '2025-01-01');
    }
}
