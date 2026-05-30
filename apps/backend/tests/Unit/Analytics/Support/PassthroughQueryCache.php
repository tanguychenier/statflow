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

namespace App\Tests\Unit\Analytics\Support;

use App\Analytics\Domain\Port\QueryCachePort;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Test double that always recomputes (no caching) but records keys and
 * invalidations, so handler behaviour is exercised end to end while cache
 * interactions remain assertable.
 */
final class PassthroughQueryCache implements QueryCachePort
{
    /**
     * @var list<array{site: string, key: string, ttl: int}>
     */
    public array $getCalls = [];

    /**
     * @var list<string>
     */
    public array $invalidatedSites = [];

    public function get(Uuid $siteId, string $key, int $ttlSeconds, callable $callback): array
    {
        $this->getCalls[] = [
            'site' => (string) $siteId,
            'key' => $key,
            'ttl' => $ttlSeconds,
        ];

        return $callback();
    }

    public function invalidateSite(Uuid $siteId): void
    {
        $this->invalidatedSites[] = (string) $siteId;
    }
}
