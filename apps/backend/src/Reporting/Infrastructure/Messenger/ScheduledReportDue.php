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

namespace App\Reporting\Infrastructure\Messenger;

/**
 * Async job message for delivering one due scheduled report. Carries the site
 * and schedule ids so the consumer can re-resolve and re-check dueness.
 */
final readonly class ScheduledReportDue
{
    public function __construct(
        public string $siteId,
        public string $scheduledReportId,
    ) {
    }
}
