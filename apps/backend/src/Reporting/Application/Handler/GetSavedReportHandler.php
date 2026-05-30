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

use App\Reporting\Application\Dto\SavedReportView;
use App\Reporting\Application\Query\GetSavedReportQuery;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Reads a single saved report, after asserting the caller may view the site and
 * the report belongs to it.
 */
final readonly class GetSavedReportHandler
{
    public function __construct(
        private SavedReportRepository $reports,
        private ReportingAccessPolicy $accessPolicy,
    ) {
    }

    public function __invoke(GetSavedReportQuery $query): SavedReportView
    {
        $userId = Uuid::fromString($query->actingUserId);
        $siteId = Uuid::fromString($query->siteId);
        $reportId = Uuid::fromString($query->reportId);

        $this->accessPolicy->assertCanView($userId, $siteId);

        $report = $this->reports->findById($siteId, $reportId);
        if ($report === null) {
            throw ReportNotFoundException::savedReport($reportId);
        }

        return SavedReportView::fromReport($report);
    }
}
