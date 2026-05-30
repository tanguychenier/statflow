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

use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemorySegmentRepository implements SegmentRepositoryPort
{
    /**
     * @var array<string, Segment> keyed by segment id
     */
    private array $segments = [];

    public function findBySite(Uuid $siteId): array
    {
        $matches = array_filter(
            $this->segments,
            static fn (Segment $s): bool => $s->siteId->equals($siteId),
        );

        return array_values($matches);
    }

    public function find(Uuid $siteId, Uuid $segmentId): ?Segment
    {
        $segment = $this->segments[(string) $segmentId] ?? null;

        return $segment !== null && $segment->siteId->equals($siteId) ? $segment : null;
    }

    public function save(Segment $segment): void
    {
        $this->segments[(string) $segment->id] = $segment;
    }

    public function delete(Uuid $siteId, Uuid $segmentId): void
    {
        $existing = $this->find($siteId, $segmentId);
        if ($existing !== null) {
            unset($this->segments[(string) $segmentId]);
        }
    }
}
