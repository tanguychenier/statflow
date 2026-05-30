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

namespace App\Reporting\Application\Command;

/**
 * Soft-delete a saved report. Idempotent at the handler level.
 */
final readonly class DeleteSavedReportCommand
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $reportId,
    ) {
    }
}
