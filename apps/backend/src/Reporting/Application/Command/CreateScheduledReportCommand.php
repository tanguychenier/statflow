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
 * Create a scheduled email report for a site.
 */
final readonly class CreateScheduledReportCommand
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $name,
        public ?string $savedReportId,
        public array $recipients,
        public string $scheduleCron,
        public string $timezone,
    ) {
    }
}
