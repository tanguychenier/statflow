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

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Model\Site;
use App\Ingestion\Domain\ValueObject\SiteKey;

/**
 * Driven port: resolves a public tracker key to the ingestion-relevant slice of
 * site configuration. The production adapter reads the `sites` / `site_settings`
 * PostgreSQL tables and is expected to cache hot keys.
 */
interface SiteRepositoryPort
{
    /**
     * @return Site|null the site if the key matches an enabled site, else null
     */
    public function findByKey(SiteKey $key): ?Site;
}
