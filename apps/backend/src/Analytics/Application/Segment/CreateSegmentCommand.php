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

use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Command: create a saved segment (OpenAPI `createSegment`).
 */
final readonly class CreateSegmentCommand
{
    public function __construct(
        public Uuid $siteId,
        public string $name,
        public FilterSet $filterSet,
        public ?string $createdBy,
    ) {
    }
}
