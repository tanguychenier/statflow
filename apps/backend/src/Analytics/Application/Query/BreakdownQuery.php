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

use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Metric;

/**
 * An {@see AnalyticsQuery} plus the breakdown dimension, ordering, and limit
 * (OpenAPI `getBreakdown`). One endpoint covers top pages / sources / devices /
 * countries by varying `property`.
 */
final readonly class BreakdownQuery
{
    public const MAX_LIMIT = 1000;

    public function __construct(
        public AnalyticsQuery $query,
        public Dimension $property,
        public Metric $sortBy,
        public bool $sortDescending,
        public int $limit,
    ) {
    }
}
