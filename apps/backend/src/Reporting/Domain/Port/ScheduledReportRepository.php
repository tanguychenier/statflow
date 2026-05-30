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

namespace App\Reporting\Domain\Port;

use App\Reporting\Domain\Model\ScheduledReport;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Driven port for scheduled-report persistence, implemented by a Doctrine
 * adapter against the PostgreSQL `scheduled_reports` table. Finders expose
 * active rows only (deleted_at IS NULL).
 */
interface ScheduledReportRepository
{
    public function save(ScheduledReport $report): void;

    public function findById(Uuid $siteId, Uuid $id): ?ScheduledReport;

    /**
     * @return ResultPage<ScheduledReport>
     */
    public function listForSite(ListCriteria $criteria): ResultPage;

    /**
     * Active, enabled schedules whose next_send_at is at or before $now, capped
     * at $limit. Drives the dispatch sweep; ordered by next_send_at ascending so
     * the most overdue runs first.
     *
     * @return list<ScheduledReport>
     */
    public function findDue(DateTimeImmutable $now, int $limit): array;
}
