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

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * In-memory {@see ScheduledReportRepository} for application-layer tests.
 */
final class InMemoryScheduledReportRepository implements ScheduledReportRepository
{
    /**
     * @var array<string, ScheduledReport>
     */
    private array $reports = [];

    public function save(ScheduledReport $report): void
    {
        $this->reports[$report->id()->getValue()] = $report;
    }

    public function findById(Uuid $siteId, Uuid $id): ?ScheduledReport
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
            static fn (ScheduledReport $r): bool => !$r->isDeleted() && $r->siteId()->equals($criteria->siteId),
        ));

        usort($matches, static fn (ScheduledReport $a, ScheduledReport $b): int => $b->createdAt() <=> $a->createdAt());

        $offset = $criteria->cursor !== null ? (int) $criteria->cursor : 0;
        $page = array_slice($matches, $offset, $criteria->limit);
        $hasMore = count($matches) > $offset + $criteria->limit;

        return new ResultPage($page, $hasMore ? (string) ($offset + $criteria->limit) : null);
    }

    public function findDue(DateTimeImmutable $now, int $limit): array
    {
        $due = array_values(array_filter(
            $this->reports,
            static fn (ScheduledReport $r): bool => $r->isDue($now),
        ));

        usort($due, static fn (ScheduledReport $a, ScheduledReport $b): int => $a->nextSendAt() <=> $b->nextSendAt());

        return array_slice($due, 0, max(1, $limit));
    }
}
