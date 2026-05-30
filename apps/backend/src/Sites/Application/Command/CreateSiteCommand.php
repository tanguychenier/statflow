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
 * Register a new site for a team. Immutable carrier; validation and
 * authorization happen in the handler. `actingUserId` is the authenticated
 * dashboard user whose role gates the operation.
 */
final readonly class CreateSiteCommand
{
    public function __construct(
        public string $actingUserId,
        public string $teamId,
        public string $name,
        public string $domain,
        public string $timezone = 'UTC',
    ) {
    }
}
