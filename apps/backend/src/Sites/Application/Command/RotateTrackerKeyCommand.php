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
 * Issue a new tracker key for a site, revoking the current one immediately
 * (ADR-0009 §1: one key per site). Admin/Owner only.
 */
final readonly class RotateTrackerKeyCommand
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
    ) {
    }
}
