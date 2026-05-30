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

use App\Reporting\Application\Command\DeleteSavedReportCommand;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Soft-deletes a saved report (editor and above). A missing report is reported
 * as not-found; the operation is otherwise idempotent.
 */
final readonly class DeleteSavedReportHandler
{
    public function __construct(
        private SavedReportRepository $reports,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteSavedReportCommand $command): void
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);
        $reportId = Uuid::fromString($command->reportId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $report = $this->reports->findById($siteId, $reportId);
        if ($report === null) {
            throw ReportNotFoundException::savedReport($reportId);
        }

        $report->softDelete($this->clock->now());

        $this->reports->save($report);
    }
}
