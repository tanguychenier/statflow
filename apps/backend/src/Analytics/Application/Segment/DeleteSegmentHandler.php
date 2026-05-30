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

namespace App\Analytics\Application\Segment;

use App\Analytics\Domain\Exception\SegmentNotFound;
use App\Analytics\Domain\Port\QueryCachePort;
use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Deletes a saved segment (OpenAPI `deleteSegment`). Idempotent at the storage
 * layer, but a missing segment is reported as 404 to the caller.
 */
final readonly class DeleteSegmentHandler
{
    public function __construct(
        private SegmentRepositoryPort $segments,
        private QueryCachePort $cache,
    ) {
    }

    public function __invoke(Uuid $siteId, Uuid $segmentId): void
    {
        if ($this->segments->find($siteId, $segmentId) === null) {
            throw SegmentNotFound::withId((string) $segmentId);
        }

        $this->segments->delete($siteId, $segmentId);
        $this->cache->invalidateSite($siteId);
    }
}
