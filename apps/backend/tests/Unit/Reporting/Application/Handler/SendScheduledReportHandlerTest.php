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

namespace App\Tests\Unit\Reporting\Application\Handler;

use App\Reporting\Application\Handler\SendScheduledReportHandler;
use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemoryScheduledReportRepository;
use App\Tests\Unit\Reporting\Fake\RecordingReportMailer;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendScheduledReportHandler::class)]
final class SendScheduledReportHandlerTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemoryScheduledReportRepository $repo;

    private RecordingReportMailer $mailer;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryScheduledReportRepository();
        $this->mailer = new RecordingReportMailer();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T09:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function itDeliversDueReportToEachRecipientAndAdvances(): void
    {
        $report = $this->persist(['a@b.com', 'c@d.com']);

        ($this->handler())->send(Uuid::fromString(self::SITE), $report->id());

        self::assertCount(2, $this->mailer->sent);
        $sent = $this->repo->findById(Uuid::fromString(self::SITE), $report->id());
        self::assertNotNull($sent);
        self::assertSame('2026-05-29T09:00:00+00:00', $sent->lastSentAt()?->format(DATE_ATOM));
        self::assertSame('2026-05-30T09:00:00+00:00', $sent->nextSendAt()?->format(DATE_ATOM));
    }

    #[Test]
    public function itSkipsWhenNotDue(): void
    {
        $report = $this->persist(['a@b.com']);
        $this->clock->set(new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));

        ($this->handler())->send(Uuid::fromString(self::SITE), $report->id());

        self::assertEmpty($this->mailer->sent);
        self::assertNull($this->repo->findById(Uuid::fromString(self::SITE), $report->id())?->lastSentAt());
    }

    #[Test]
    public function itAdvancesEvenWhenMailerUnconfigured(): void
    {
        $this->mailer->configured(false);
        $report = $this->persist(['a@b.com']);

        ($this->handler())->send(Uuid::fromString(self::SITE), $report->id());

        self::assertEmpty($this->mailer->sent);
        self::assertNotNull($this->repo->findById(Uuid::fromString(self::SITE), $report->id())?->lastSentAt());
    }

    #[Test]
    public function itAdvancesDespiteTransportFailure(): void
    {
        $this->mailer->failing(true);
        $report = $this->persist(['a@b.com']);

        ($this->handler())->send(Uuid::fromString(self::SITE), $report->id());

        self::assertNotNull($this->repo->findById(Uuid::fromString(self::SITE), $report->id())?->lastSentAt());
    }

    #[Test]
    public function itIgnoresUnknownSchedule(): void
    {
        ($this->handler())->send(Uuid::fromString(self::SITE), Uuid::generate());

        self::assertEmpty($this->mailer->sent);
    }

    /**
     * @param list<string> $recipients
     */
    private function persist(array $recipients): ScheduledReport
    {
        $report = ScheduledReport::schedule(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            null,
            ReportName::fromString('Weekly'),
            EmailRecipientList::fromStrings($recipients),
            CronExpression::fromString('0 9 * * *'),
            ReportTimezone::fromString('UTC'),
            null,
            new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
        $this->repo->save($report);

        return $report;
    }

    private function handler(): SendScheduledReportHandler
    {
        return new SendScheduledReportHandler($this->repo, $this->mailer, $this->clock);
    }
}
