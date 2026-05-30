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

use App\Analytics\Domain\Exception\InvalidSegment;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Parses the OpenAPI `SegmentCreateRequest` body into a {@see CreateSegmentCommand}.
 */
final class SegmentRequestFactory
{
    /**
     * @param array<string, mixed> $body
     */
    public function createCommand(Uuid $siteId, array $body, ?string $createdBy): CreateSegmentCommand
    {
        $name = $body['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            throw InvalidSegment::emptyName();
        }

        $rawFilters = $body['filters'] ?? null;
        if (!is_array($rawFilters) || $rawFilters === []) {
            throw InvalidSegment::noFilters();
        }

        /** @var list<array<string, mixed>> $filters */
        $filters = [];
        foreach (array_values($rawFilters) as $entry) {
            if (!is_array($entry)) {
                throw InvalidSegment::noFilters();
            }
            /** @var array<string, mixed> $entry */
            $filters[] = $entry;
        }

        $combination = is_string($body['filter_combination'] ?? null) ? $body['filter_combination'] : 'and';

        return new CreateSegmentCommand(
            $siteId,
            $name,
            FilterSet::fromArray($filters, $combination),
            $createdBy,
        );
    }
}
