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

namespace App\Tests\Unit\Analytics\Infrastructure\Cache;

use App\Analytics\Infrastructure\Cache\TaggedQueryCache;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * Runs in-process against an array-backed tag-aware pool, so the cache contract
 * (memoise, invalidate-by-site, cross-site isolation) is verified without a
 * Redis dependency.
 */
#[CoversClass(TaggedQueryCache::class)]
final class TaggedQueryCacheTest extends TestCase
{
    private TaggedQueryCache $cache;

    private int $calls = 0;

    protected function setUp(): void
    {
        $this->cache = new TaggedQueryCache(new TagAwareAdapter(new ArrayAdapter()));
        $this->calls = 0;
    }

    #[Test]
    public function itMemoisesAResultForTheSameKey(): void
    {
        $site = Uuid::generate();

        $first = $this->cache->get($site, 'k1', 60, $this->counter());
        $second = $this->cache->get($site, 'k1', 60, $this->counter());

        self::assertSame($first, $second);
        self::assertSame(1, $this->calls);
    }

    #[Test]
    public function invalidateSiteForcesRecomputation(): void
    {
        $site = Uuid::generate();

        $this->cache->get($site, 'k1', 60, $this->counter());
        $this->cache->invalidateSite($site);
        $this->cache->get($site, 'k1', 60, $this->counter());

        self::assertSame(2, $this->calls);
    }

    #[Test]
    public function invalidatingOneSiteLeavesAnotherCached(): void
    {
        $siteA = Uuid::generate();
        $siteB = Uuid::generate();

        $this->cache->get($siteA, 'k', 60, $this->counter());
        $this->cache->get($siteB, 'k', 60, $this->counter());
        $this->cache->invalidateSite($siteA);
        $this->cache->get($siteB, 'k', 60, $this->counter());

        self::assertSame(2, $this->calls);
    }

    private function counter(): callable
    {
        return function (): array {
            ++$this->calls;

            return [
                'value' => $this->calls,
            ];
        };
    }
}
