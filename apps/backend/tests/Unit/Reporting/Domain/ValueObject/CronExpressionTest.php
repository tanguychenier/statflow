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

namespace App\Tests\Unit\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidScheduleException;
use App\Reporting\Domain\ValueObject\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CronExpression::class)]
#[CoversClass(InvalidScheduleException::class)]
final class CronExpressionTest extends TestCase
{
    #[Test]
    public function itComputesNextDailyRunInUtc(): void
    {
        $cron = CronExpression::fromString('0 9 * * *');
        $after = new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));

        $next = $cron->nextRunAfter($after, new DateTimeZone('UTC'));

        self::assertSame('2026-05-29T09:00:00+00:00', $next->format(DATE_ATOM));
    }

    #[Test]
    public function itRollsToNextDayWhenSlotPassed(): void
    {
        $cron = CronExpression::fromString('30 6 * * *');
        $after = new DateTimeImmutable('2026-05-29T07:00:00', new DateTimeZone('UTC'));

        $next = $cron->nextRunAfter($after, new DateTimeZone('UTC'));

        self::assertSame('2026-05-30T06:30:00+00:00', $next->format(DATE_ATOM));
    }

    #[Test]
    public function itHonoursTheScheduleTimezone(): void
    {
        // 09:00 Europe/Paris (UTC+2 in summer) is 07:00 UTC.
        $cron = CronExpression::fromString('0 9 * * *');
        $after = new DateTimeImmutable('2026-05-29T00:00:00', new DateTimeZone('UTC'));

        $next = $cron->nextRunAfter($after, new DateTimeZone('Europe/Paris'));

        self::assertSame('2026-05-29T07:00:00+00:00', $next->format(DATE_ATOM));
    }

    #[Test]
    public function itMatchesDayOfWeek(): void
    {
        // Monday at 09:00. 2026-06-01 is a Monday.
        $cron = CronExpression::fromString('0 9 * * 1');
        $after = new DateTimeImmutable('2026-05-29T00:00:00', new DateTimeZone('UTC'));

        $next = $cron->nextRunAfter($after, new DateTimeZone('UTC'));

        self::assertSame('2026-06-01T09:00:00+00:00', $next->format(DATE_ATOM));
        self::assertSame('Mon', $next->format('D'));
    }

    #[Test]
    public function itSupportsListsInDayOfWeek(): void
    {
        $cron = CronExpression::fromString('0 9 * * 1,3,5');

        self::assertSame('0 9 * * 1,3,5', $cron->value());
    }

    #[Test]
    public function itRejectsSubDailySchedules(): void
    {
        $this->expectException(InvalidScheduleException::class);
        CronExpression::fromString('*/15 * * * *');
    }

    #[Test]
    public function itRejectsWrongFieldCount(): void
    {
        $this->expectException(InvalidScheduleException::class);
        CronExpression::fromString('0 9 * *');
    }

    #[Test]
    public function itRejectsOutOfRangeValue(): void
    {
        $this->expectException(InvalidScheduleException::class);
        CronExpression::fromString('0 25 * * *');
    }

    #[Test]
    public function itRejectsInvertedRange(): void
    {
        $this->expectException(InvalidScheduleException::class);
        CronExpression::fromString('0 9 10-5 * *');
    }

    #[Test]
    public function itRejectsZeroStep(): void
    {
        $this->expectException(InvalidScheduleException::class);
        CronExpression::fromString('0 9 */0 * *');
    }
}
