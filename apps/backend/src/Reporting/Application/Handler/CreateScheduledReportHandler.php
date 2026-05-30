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

use App\Reporting\Application\Command\CreateScheduledReportCommand;
use App\Reporting\Application\Dto\ScheduledReportView;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Creates a scheduled email report (editor and above). When a saved report is
 * referenced it must belong to the same site; the schedule's first send time is
 * computed from the cron at creation.
 */
final readonly class CreateScheduledReportHandler
{
    public function __construct(
        private ScheduledReportRepository $schedules,
        private SavedReportRepository $savedReports,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateScheduledReportCommand $command): ScheduledReportView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $savedReportId = $this->resolveSavedReport($siteId, $command->savedReportId);

        $schedule = ScheduledReport::schedule(
            id: Uuid::generate(),
            siteId: $siteId,
            savedReportId: $savedReportId,
            name: ReportName::fromString($command->name),
            recipients: EmailRecipientList::fromStrings($command->recipients),
            schedule: CronExpression::fromString($command->scheduleCron),
            timezone: ReportTimezone::fromString($command->timezone),
            createdBy: $userId,
            now: $this->clock->now(),
        );

        $this->schedules->save($schedule);

        return ScheduledReportView::fromReport($schedule);
    }

    private function resolveSavedReport(Uuid $siteId, ?string $savedReportId): ?Uuid
    {
        if ($savedReportId === null) {
            return null;
        }

        $id = Uuid::fromString($savedReportId);

        if ($this->savedReports->findById($siteId, $id) === null) {
            throw ReportNotFoundException::savedReport($id);
        }

        return $id;
    }
}
