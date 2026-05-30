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

use App\Reporting\Domain\Model\Alert;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port for alert persistence, implemented by a Doctrine adapter against
 * the PostgreSQL `alerts` table. Finders expose active rows only (deleted_at IS
 * NULL).
 */
interface AlertRepository
{
    public function save(Alert $alert): void;

    public function findById(Uuid $siteId, Uuid $id): ?Alert;

    /**
     * @return ResultPage<Alert>
     */
    public function listForSite(ListCriteria $criteria): ResultPage;

    /**
     * Every active, enabled alert for a site. Drives the evaluation pass.
     *
     * @return list<Alert>
     */
    public function findEnabledForSite(Uuid $siteId): array;
}
