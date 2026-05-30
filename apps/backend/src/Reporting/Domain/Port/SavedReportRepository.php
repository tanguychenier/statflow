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

use App\Reporting\Domain\Model\SavedReport;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port for saved-report persistence, implemented by a Doctrine adapter
 * against the PostgreSQL `saved_reports` table. Finders expose active rows only
 * (deleted_at IS NULL).
 */
interface SavedReportRepository
{
    public function save(SavedReport $report): void;

    /**
     * Active report by id scoped to its site, or null when missing/deleted or
     * the id belongs to a different site.
     */
    public function findById(Uuid $siteId, Uuid $id): ?SavedReport;

    /**
     * @return ResultPage<SavedReport>
     */
    public function listForSite(ListCriteria $criteria): ResultPage;
}
