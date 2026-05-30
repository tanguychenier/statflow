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

namespace App\Sites\Application\Command;

/**
 * Soft-delete a site and schedule removal of its analytical data (OpenAPI
 * deleteSite). Owner only; destructive and irreversible.
 */
final readonly class DeleteSiteCommand
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
    ) {
    }
}
