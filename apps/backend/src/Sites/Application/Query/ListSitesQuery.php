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
 * List the sites the authenticated user can access, optionally narrowed to a
 * single team and/or filtered by a name/domain search. Keyset paginated.
 */
final readonly class ListSitesQuery
{
    public function __construct(
        public string $actingUserId,
        public ?string $teamId = null,
        public ?string $search = null,
        public ?string $cursor = null,
        public int $limit = 25,
    ) {
    }
}
