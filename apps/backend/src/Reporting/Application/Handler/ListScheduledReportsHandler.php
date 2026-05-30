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
use App\Reporting\Application\Dto\ScheduledReportView;
use App\Reporting\Application\Query\ListScheduledReportsQuery;
use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists a site's scheduled reports for any accepted team member, keyset
 * paginated.
 */
final readonly class ListScheduledReportsHandler
{
    public function __construct(
        private ScheduledReportRepository $schedules,
        private ReportingAccessPolicy $accessPolicy,
    ) {
    }

    public function __invoke(ListScheduledReportsQuery $query): PaginatedView
    {
        $userId = Uuid::fromString($query->actingUserId);
        $siteId = Uuid::fromString($query->siteId);

        $this->accessPolicy->assertCanView($userId, $siteId);

        $criteria = ListCriteria::create($siteId, $query->cursor, $query->limit);
        $page = $this->schedules->listForSite($criteria);

        return new PaginatedView(
            data: array_map(
                static fn (ScheduledReport $r): array => ScheduledReportView::fromReport($r)->toArray(),
                $page->items,
            ),
            nextCursor: $page->nextCursor,
            limit: $criteria->limit,
        );
    }
}
