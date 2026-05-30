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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory {@see SavedReportRepository} with an offset cursor, reproducing the
 * adapter's active-only visibility and (created_at DESC, id DESC) ordering.
 */
final class InMemorySavedReportRepository implements SavedReportRepository
{
    /**
     * @var array<string, SavedReport>
     */
    private array $reports = [];

    public function save(SavedReport $report): void
    {
        $this->reports[$report->id()->getValue()] = $report;
    }

    public function findById(Uuid $siteId, Uuid $id): ?SavedReport
    {
        $report = $this->reports[$id->getValue()] ?? null;

        if ($report === null || $report->isDeleted() || !$report->siteId()->equals($siteId)) {
            return null;
        }

        return $report;
    }

    public function listForSite(ListCriteria $criteria): ResultPage
    {
        $matches = array_values(array_filter(
            $this->reports,
            static fn (SavedReport $r): bool => !$r->isDeleted() && $r->siteId()->equals($criteria->siteId),
        ));

        usort($matches, static fn (SavedReport $a, SavedReport $b): int => $b->createdAt() <=> $a->createdAt());

        $offset = $criteria->cursor !== null ? (int) $criteria->cursor : 0;
        $page = array_slice($matches, $offset, $criteria->limit);
        $hasMore = count($matches) > $offset + $criteria->limit;

        return new ResultPage($page, $hasMore ? (string) ($offset + $criteria->limit) : null);
    }
}
