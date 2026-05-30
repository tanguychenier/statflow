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

namespace App\Tests\Ingestion\Support;

use App\Ingestion\Domain\Model\Site;
use App\Ingestion\Domain\Port\SiteRepositoryPort;
use App\Ingestion\Domain\ValueObject\SiteKey;

/**
 * Test double for SiteRepositoryPort keyed by tracker key string.
 */
final class InMemorySiteRepository implements SiteRepositoryPort
{
    /**
     * @var array<string, Site>
     */
    private array $sites = [];

    public function add(Site $site): void
    {
        $this->sites[$site->trackerKey] = $site;
    }

    public function findByKey(SiteKey $key): ?Site
    {
        return $this->sites[$key->value()] ?? null;
    }
}
