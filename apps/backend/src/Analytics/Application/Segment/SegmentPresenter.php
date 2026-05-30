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

use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\ValueObject\Filter;

/**
 * Maps a {@see Segment} aggregate to its OpenAPI `Segment` JSON shape.
 */
final class SegmentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Segment $segment): array
    {
        return [
            'id' => (string) $segment->id,
            'site_id' => (string) $segment->siteId,
            'name' => $segment->name,
            'filters' => array_map(self::filterToArray(...), $segment->filterSet->filters),
            'filter_combination' => $segment->filterSet->combination->value,
            'created_by' => $segment->createdBy,
            'created_at' => $segment->createdAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function filterToArray(Filter $filter): array
    {
        return [
            'property' => $filter->dimension->value,
            'operator' => $filter->operator->value,
            'value' => $filter->value,
        ];
    }
}
