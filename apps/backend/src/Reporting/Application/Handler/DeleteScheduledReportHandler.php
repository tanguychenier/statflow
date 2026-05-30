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

use App\Reporting\Application\Command\DeleteScheduledReportCommand;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Soft-deletes a scheduled report (editor and above), which also deactivates it
 * so the dispatcher never picks it up again.
 */
final readonly class DeleteScheduledReportHandler
{
    public function __construct(
        private ScheduledReportRepository $schedules,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteScheduledReportCommand $command): void
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);
        $scheduleId = Uuid::fromString($command->scheduledReportId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $schedule = $this->schedules->findById($siteId, $scheduleId);
        if ($schedule === null) {
            throw ReportNotFoundException::scheduledReport($scheduleId);
        }

        $schedule->softDelete($this->clock->now());

        $this->schedules->save($schedule);
    }
}
