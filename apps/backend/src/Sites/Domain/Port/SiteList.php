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

namespace App\Sites\Domain\Port;

use App\Sites\Domain\Model\Site;

/**
 * A single page of sites plus the cursor needed to fetch the following page.
 * `nextCursor` is null when the page is the last one.
 */
final readonly class SiteList
{
    /**
     * @param list<Site> $sites
     */
    public function __construct(
        public array $sites,
        public ?string $nextCursor,
    ) {
    }
}
