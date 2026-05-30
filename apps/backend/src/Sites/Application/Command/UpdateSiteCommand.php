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
 * Partial update of a site's metadata (OpenAPI PATCH SiteUpdateRequest).
 * Every field is optional: a null value means "leave unchanged".
 */
final readonly class UpdateSiteCommand
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public ?string $name = null,
        public ?string $domain = null,
        public ?string $timezone = null,
    ) {
    }
}
