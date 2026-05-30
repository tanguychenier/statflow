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
 * List the scheduled reports of a site. Keyset paginated.
 */
final readonly class ListScheduledReportsQuery
{
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public ?string $cursor = null,
        public int $limit = 25,
    ) {
    }
}
