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

namespace App\Analytics\Application\Query;

use App\Analytics\Domain\Exception\SegmentNotFound;
use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Resolves a query's optional saved-segment reference into its concrete
 * {@see FilterSet}, and merges it with the query's inline filters as a list of
 * AND-ed groups ready for the {@see \App\Analytics\Domain\Query\FilterCompiler}.
 */
final readonly class SegmentResolver
{
    public function __construct(
        private SegmentRepositoryPort $segments,
    ) {
    }

    /**
     * @return list<FilterSet> the segment group (if any) plus the inline group
     */
    public function resolveGroups(Uuid $siteId, ?Uuid $segmentId, FilterSet $inline): array
    {
        if ($segmentId === null) {
            return $inline->isEmpty() ? [] : [$inline];
        }

        $segment = $this->segments->find($siteId, $segmentId);
        if ($segment === null) {
            throw SegmentNotFound::withId((string) $segmentId);
        }

        return $segment->filterSet->andGroups($inline);
    }
}
