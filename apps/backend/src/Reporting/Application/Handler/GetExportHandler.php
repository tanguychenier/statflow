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

use App\Reporting\Application\Dto\ExportView;
use App\Reporting\Application\Query\GetExportQuery;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Port\ExportArtifactStorage;
use App\Reporting\Domain\Port\ExportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Reads an export job's status and, when completed, a freshly-minted download
 * URL. Any accepted member of the site's team may poll.
 */
final readonly class GetExportHandler
{
    public function __construct(
        private ExportRepository $exports,
        private ExportArtifactStorage $storage,
        private ReportingAccessPolicy $accessPolicy,
    ) {
    }

    public function __invoke(GetExportQuery $query): ExportView
    {
        $userId = Uuid::fromString($query->actingUserId);
        $siteId = Uuid::fromString($query->siteId);
        $exportId = Uuid::fromString($query->exportId);

        $this->accessPolicy->assertCanView($userId, $siteId);

        $export = $this->exports->findById($siteId, $exportId);
        if ($export === null) {
            throw ReportNotFoundException::export($exportId);
        }

        return ExportView::fromExport($export, $this->storage);
    }
}
