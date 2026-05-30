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

namespace App\Reporting\Application\Query;

/**
 * Fetch the status (and, when ready, download URL) of an export job.
 */
final readonly class GetExportQuery
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $exportId,
    ) {
    }
}
