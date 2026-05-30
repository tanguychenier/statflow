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

use App\Analytics\Domain\Model\Segment;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Port (driven side) for persisting and loading saved segments
 * (`postgres.segments`). Implemented by a Doctrine adapter.
 */
interface SegmentRepositoryPort
{
    /**
     * @return list<Segment> ordered by creation time, newest first
     */
    public function findBySite(Uuid $siteId): array;

    public function find(Uuid $siteId, Uuid $segmentId): ?Segment;

    public function save(Segment $segment): void;

    /**
     * Soft-delete the segment. No-op if it does not exist.
     */
    public function delete(Uuid $siteId, Uuid $segmentId): void;
}
