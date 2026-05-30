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

namespace App\Analytics\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Port (driven side) for the analytics query-result cache.
 *
 * Results are memoised per `(site, query fingerprint)`. Entries carry the
 * site's id as a cache tag so the whole site can be invalidated when fresh
 * events land (the ingestion side calls {@see invalidateSite()} on flush).
 */
interface QueryCachePort
{
    /**
     * Return the cached value for `$key` under `$siteId`, or compute it via
     * `$callback`, store it with the given TTL, and return it.
     *
     * @param callable(): array<array-key, mixed> $callback
     *
     * @return array<array-key, mixed>
     */
    public function get(Uuid $siteId, string $key, int $ttlSeconds, callable $callback): array;

    /**
     * Drop every cached query result for a site (called when new events are
     * ingested so dashboards never serve stale aggregates beyond the TTL).
     */
    public function invalidateSite(Uuid $siteId): void;
}
