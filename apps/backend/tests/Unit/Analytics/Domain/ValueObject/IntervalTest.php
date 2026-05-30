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

use App\Analytics\Domain\Exception\UnknownInterval;
use App\Analytics\Domain\ValueObject\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interval::class)]
#[CoversClass(UnknownInterval::class)]
final class IntervalTest extends TestCase
{
    #[Test]
    public function itParsesAKnownInterval(): void
    {
        self::assertSame(Interval::Hour, Interval::fromString('hour'));
    }

    #[Test]
    public function itRejectsAnUnknownInterval(): void
    {
        $this->expectException(UnknownInterval::class);

        Interval::fromString('fortnight');
    }

    #[Test]
    #[DataProvider('autoSelectCases')]
    public function itAutoSelectsTheBucketBySpan(int $days, Interval $expected): void
    {
        self::assertSame($expected, Interval::autoSelect($days));
    }

    /**
     * @return iterable<string, array{int, Interval}>
     */
    public static function autoSelectCases(): iterable
    {
        yield 'single day' => [1, Interval::Hour];
        yield 'two months' => [60, Interval::Day];
        yield 'half year' => [180, Interval::Week];
        yield 'multi year' => [400, Interval::Month];
    }

    #[Test]
    #[DataProvider('bucketFunctionCases')]
    public function itMapsToTheClickHouseBucketFunction(Interval $interval, string $expected): void
    {
        self::assertSame($expected, $interval->clickHouseBucketFunction());
    }

    /**
     * @return iterable<string, array{Interval, string}>
     */
    public static function bucketFunctionCases(): iterable
    {
        yield 'minute' => [Interval::Minute, 'toStartOfMinute'];
        yield 'hour' => [Interval::Hour, 'toStartOfHour'];
        yield 'day' => [Interval::Day, 'toStartOfDay'];
        yield 'week' => [Interval::Week, 'toStartOfWeek'];
        yield 'month' => [Interval::Month, 'toStartOfMonth'];
    }
}
