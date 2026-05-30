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

use App\Reporting\Application\Dto\PaginatedView;
use App\Reporting\Application\Dto\SavedReportView;
use App\Reporting\Application\Query\ListSavedReportsQuery;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists a site's saved reports for any accepted team member, keyset paginated.
 */
final readonly class ListSavedReportsHandler
{
    public function __construct(
        private SavedReportRepository $reports,
        private ReportingAccessPolicy $accessPolicy,
    ) {
    }

    public function __invoke(ListSavedReportsQuery $query): PaginatedView
    {
        $userId = Uuid::fromString($query->actingUserId);
        $siteId = Uuid::fromString($query->siteId);

        $this->accessPolicy->assertCanView($userId, $siteId);

        $criteria = ListCriteria::create($siteId, $query->cursor, $query->limit);
        $page = $this->reports->listForSite($criteria);

        return new PaginatedView(
            data: array_map(
                static fn (SavedReport $r): array => SavedReportView::fromReport($r)->toArray(),
                $page->items,
            ),
            nextCursor: $page->nextCursor,
            limit: $criteria->limit,
        );
    }
}
