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

namespace App\Reporting\Application\Query;

/**
 * List the saved reports of a site the authenticated user can read. Keyset
 * paginated.
 */
final readonly class ListSavedReportsQuery
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public ?string $cursor = null,
        public int $limit = 25,
    ) {
    }
}
