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

namespace App\Tests\Unit\Reporting\Domain\Model;

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScheduledReport::class)]
final class ScheduledReportTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function itComputesNextSendOnCreation(): void
    {
        $report = $this->schedule('0 9 * * *', new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));

        self::assertSame('2026-05-29T09:00:00+00:00', $report->nextSendAt()?->format(DATE_ATOM));
        self::assertTrue($report->isActive());
        self::assertNull($report->lastSentAt());
    }

    #[Test]
    public function markSentAdvancesSchedule(): void
    {
        $report = $this->schedule('0 9 * * *', new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));

        $report->markSent(new DateTimeImmutable('2026-05-29T09:00:05', new DateTimeZone('UTC')));

        self::assertSame('2026-05-29T09:00:05+00:00', $report->lastSentAt()?->format(DATE_ATOM));
        self::assertSame('2026-05-30T09:00:00+00:00', $report->nextSendAt()?->format(DATE_ATOM));
    }

    #[Test]
    public function isDueReflectsClockAndState(): void
    {
        $report = $this->schedule('0 9 * * *', new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));

        self::assertFalse($report->isDue(new DateTimeImmutable('2026-05-29T08:59:00', new DateTimeZone('UTC'))));
        self::assertTrue($report->isDue(new DateTimeImmutable('2026-05-29T09:00:00', new DateTimeZone('UTC'))));
    }

    #[Test]
    public function deactivateClearsNextSend(): void
    {
        $now = new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
        $report = $this->schedule('0 9 * * *', $now);

        $report->deactivate($now);

        self::assertFalse($report->isActive());
        self::assertNull($report->nextSendAt());
        self::assertFalse($report->isDue(new DateTimeImmutable('2026-05-29T10:00:00', new DateTimeZone('UTC'))));

        $report->activate($now);
        self::assertTrue($report->isActive());
        self::assertNotNull($report->nextSendAt());
    }

    #[Test]
    public function rescheduleRecomputesNextSend(): void
    {
        $now = new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
        $report = $this->schedule('0 9 * * *', $now);

        $report->reschedule(CronExpression::fromString('0 18 * * *'), ReportTimezone::fromString('UTC'), $now);

        self::assertSame('2026-05-29T18:00:00+00:00', $report->nextSendAt()?->format(DATE_ATOM));
    }

    #[Test]
    public function softDeleteDeactivates(): void
    {
        $now = new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
        $report = $this->schedule('0 9 * * *', $now);

        $report->softDelete($now);

        self::assertTrue($report->isDeleted());
        self::assertFalse($report->isActive());
        self::assertNull($report->nextSendAt());
    }

    private function schedule(string $cron, DateTimeImmutable $now): ScheduledReport
    {
        return ScheduledReport::schedule(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            savedReportId: null,
            name: ReportName::fromString('Weekly'),
            recipients: EmailRecipientList::fromStrings(['a@b.com']),
            schedule: CronExpression::fromString($cron),
            timezone: ReportTimezone::fromString('UTC'),
            createdBy: null,
            now: $now,
        );
    }
}
