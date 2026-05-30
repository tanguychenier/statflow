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
 * Partially update a scheduled report (OpenAPI PATCH semantics: only the
 * provided fields change). Null fields are left untouched; cron and timezone
 * must be supplied together when either changes, which the handler enforces.
 */
final readonly class UpdateScheduledReportCommand
{
    /**
     * @param list<string>|null $recipients
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $scheduledReportId,
        public ?string $name = null,
        public ?array $recipients = null,
        public ?string $scheduleCron = null,
        public ?string $timezone = null,
        public ?bool $isActive = null,
    ) {
    }
}
