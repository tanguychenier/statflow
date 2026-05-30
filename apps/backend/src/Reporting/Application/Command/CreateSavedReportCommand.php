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
 * Save a report configuration for a site. Immutable carrier; validation and
 * authorization happen in the handler. `actingUserId` is the authenticated
 * dashboard user whose role gates the operation and is recorded as the author.
 */
final readonly class CreateSavedReportCommand
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $name,
        public ?string $description,
        public string $reportType,
        public array $query,
    ) {
    }
}
