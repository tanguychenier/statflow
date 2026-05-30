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

namespace App\Reporting\Application\Handler;

use App\Reporting\Application\Command\UpdateScheduledReportCommand;
use App\Reporting\Application\Dto\ScheduledReportView;
use App\Reporting\Domain\Exception\InvalidScheduleException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Applies a partial update to a scheduled report (editor and above). Each field
 * is optional; cron and timezone must change together so the schedule's next
 * send time can be recomputed coherently.
 */
final readonly class UpdateScheduledReportHandler
{
    public function __construct(
        private ScheduledReportRepository $schedules,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateScheduledReportCommand $command): ScheduledReportView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);
        $scheduleId = Uuid::fromString($command->scheduledReportId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $schedule = $this->schedules->findById($siteId, $scheduleId);
        if ($schedule === null) {
            throw ReportNotFoundException::scheduledReport($scheduleId);
        }

        $now = $this->clock->now();

        $this->applyName($schedule, $command, $now);
        $this->applyRecipients($schedule, $command, $now);
        $this->applySchedule($schedule, $command, $now);
        $this->applyActivation($schedule, $command, $now);

        $this->schedules->save($schedule);

        return ScheduledReportView::fromReport($schedule);
    }

    private function applyName(ScheduledReport $schedule, UpdateScheduledReportCommand $command, \DateTimeImmutable $now): void
    {
        if ($command->name !== null) {
            $schedule->rename(ReportName::fromString($command->name), $now);
        }
    }

    private function applyRecipients(ScheduledReport $schedule, UpdateScheduledReportCommand $command, \DateTimeImmutable $now): void
    {
        if ($command->recipients !== null) {
            $schedule->changeRecipients(EmailRecipientList::fromStrings($command->recipients), $now);
        }
    }

    private function applySchedule(ScheduledReport $schedule, UpdateScheduledReportCommand $command, \DateTimeImmutable $now): void
    {
        if ($command->scheduleCron === null && $command->timezone === null) {
            return;
        }

        if ($command->scheduleCron === null || $command->timezone === null) {
            throw InvalidScheduleException::cronAndTimezoneTogether();
        }

        $schedule->reschedule(
            CronExpression::fromString($command->scheduleCron),
            ReportTimezone::fromString($command->timezone),
            $now,
        );
    }

    private function applyActivation(ScheduledReport $schedule, UpdateScheduledReportCommand $command, \DateTimeImmutable $now): void
    {
        if ($command->isActive === null) {
            return;
        }

        if ($command->isActive) {
            $schedule->activate($now);
        } else {
            $schedule->deactivate($now);
        }
    }
}
