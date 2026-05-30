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

namespace App\Sites\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port that schedules the asynchronous removal of a deleted site's
 * analytical data from ClickHouse (OpenAPI deleteSite: removal may take up to
 * 24h to propagate). The Sites context only soft-deletes the PostgreSQL row and
 * hands the site id to this scheduler; the actual purge is another context's job.
 */
interface SiteDataDeletionScheduler
{
    public function scheduleDeletion(Uuid $siteId): void;
}
