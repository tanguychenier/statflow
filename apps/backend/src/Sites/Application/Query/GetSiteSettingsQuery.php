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

namespace App\Sites\Application\Query;

/**
 * Fetch a site's full settings on behalf of the authenticated user.
 */
final readonly class GetSiteSettingsQuery
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
    ) {
    }
}
