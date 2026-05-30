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

use App\Reporting\Domain\Model\Export;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port for export-job persistence, implemented by a Doctrine adapter
 * against the PostgreSQL `exports` table. Exports are never soft-deleted; they
 * expire by retention policy instead.
 */
interface ExportRepository
{
    public function save(Export $export): void;

    /**
     * Export by id scoped to its site, or null when missing or owned by another
     * site.
     */
    public function findById(Uuid $siteId, Uuid $id): ?Export;

    /**
     * Export by id without a site scope. Used by the async worker, which already
     * holds a trusted id from the job message.
     */
    public function findByIdUnscoped(Uuid $id): ?Export;
}
