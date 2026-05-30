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

use App\Reporting\Application\Command\CreateSavedReportCommand;
use App\Reporting\Application\Dto\SavedReportView;
use App\Reporting\Domain\Model\ReportType;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportDescription;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Persists a new saved report after authorizing the caller (editor and above)
 * and validating its name, type and query definition.
 */
final readonly class CreateSavedReportHandler
{
    public function __construct(
        private SavedReportRepository $reports,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateSavedReportCommand $command): SavedReportView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $report = SavedReport::create(
            id: Uuid::generate(),
            siteId: $siteId,
            name: ReportName::fromString($command->name),
            description: ReportDescription::fromNullableString($command->description),
            reportType: ReportType::fromString($command->reportType),
            definition: QueryDefinition::fromArray($command->query),
            createdBy: $userId,
            now: $this->clock->now(),
        );

        $this->reports->save($report);

        return SavedReportView::fromReport($report);
    }
}
