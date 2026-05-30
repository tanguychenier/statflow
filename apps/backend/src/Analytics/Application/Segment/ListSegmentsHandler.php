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

use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists the saved segments for a site (OpenAPI `listSegments`).
 */
final readonly class ListSegmentsHandler
{
    public function __construct(
        private SegmentRepositoryPort $segments,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(Uuid $siteId): array
    {
        return array_map(
            SegmentPresenter::toArray(...),
            $this->segments->findBySite($siteId),
        );
    }
}
