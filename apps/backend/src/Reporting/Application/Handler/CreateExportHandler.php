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

use App\Reporting\Application\Command\CreateExportCommand;
use App\Reporting\Application\Dto\ExportView;
use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ExportJobDispatcher;
use App\Reporting\Domain\Port\ExportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Accepts an export request (editor and above): persists the job as `pending`
 * and hands it to the background dispatcher. Returns immediately with the job
 * view; the caller polls for completion. The persisted row is the source of
 * truth, so the dispatch is fired after the save commits.
 */
final readonly class CreateExportHandler
{
    public function __construct(
        private ExportRepository $exports,
        private ExportJobDispatcher $dispatcher,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateExportCommand $command): ExportView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $notifyEmail = $command->notifyEmail !== null
            ? EmailAddress::fromString($command->notifyEmail)
            : null;

        $export = Export::request(
            id: Uuid::generate(),
            siteId: $siteId,
            format: ExportFormat::fromString($command->format),
            query: QueryDefinition::fromArray($command->query),
            notifyEmail: $notifyEmail,
            createdBy: $userId,
            now: $this->clock->now(),
        );

        $this->exports->save($export);
        $this->dispatcher->dispatch($export->id());

        return ExportView::fromExport($export);
    }
}
