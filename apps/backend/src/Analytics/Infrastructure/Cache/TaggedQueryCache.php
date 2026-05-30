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

namespace App\Analytics\Infrastructure\Cache;

use App\Analytics\Domain\Port\QueryCachePort;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Query-result cache backed by a tag-aware PSR-6 pool (Redis in production).
 *
 * Every entry is tagged with its site so {@see invalidateSite()} drops a whole
 * site's memoised results atomically — the ingestion flush calls it when fresh
 * events land, keeping dashboards from serving stale aggregates beyond the TTL.
 */
final readonly class TaggedQueryCache implements QueryCachePort
{
    public function __construct(
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function get(Uuid $siteId, string $key, int $ttlSeconds, callable $callback): array
    {
        $cacheKey = $this->cacheKey($siteId, $key);

        /** @var array<array-key, mixed> $value */
        $value = $this->cache->get(
            $cacheKey,
            static function (ItemInterface $item) use ($ttlSeconds, $siteId, $callback): array {
                $item->expiresAfter($ttlSeconds);
                $item->tag(self::siteTag($siteId));

                return $callback();
            },
        );

        return $value;
    }

    public function invalidateSite(Uuid $siteId): void
    {
        $this->cache->invalidateTags([self::siteTag($siteId)]);
    }

    private function cacheKey(Uuid $siteId, string $key): string
    {
        // PSR-6 reserves {}()/\@: in keys. Query keys embed ':' separators
        // (e.g. "aggregate:<fingerprint>"), so map every reserved character to
        // '.' to keep the composed key valid for the cache pool.
        return strtr(sprintf('analytics.%s.%s', $siteId, $key), '{}()/\\@:', '........');
    }

    private static function siteTag(Uuid $siteId): string
    {
        return 'analytics-site-' . $siteId;
    }
}
