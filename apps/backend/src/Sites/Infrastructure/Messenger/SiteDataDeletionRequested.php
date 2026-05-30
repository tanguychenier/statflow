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

namespace App\Sites\Infrastructure\Messenger;

/**
 * Integration message announcing that a site has been deleted and its
 * analytical data must be purged from ClickHouse. Consumed asynchronously by
 * the analytical-store purge worker (a different context). Carries only the
 * site id so it is safe to (de)serialise across process boundaries.
 */
final readonly class SiteDataDeletionRequested
{
    public function __construct(
        public string $siteId,
    ) {
    }
}
