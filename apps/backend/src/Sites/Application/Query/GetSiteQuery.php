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
 * Fetch a single site by id on behalf of the authenticated user. Returns a
 * not-found if the user is not a member of the owning team.
 */
final readonly class GetSiteQuery
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
    ) {
    }
}
