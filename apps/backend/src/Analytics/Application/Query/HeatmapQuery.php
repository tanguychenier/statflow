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

use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\HeatmapType;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Click/scroll heatmap query for one page (OpenAPI `queryHeatmap`).
 *
 * Viewport bounds narrow the click heatmap to a device-class band. Because the
 * `heatmap_stats` grid is built from resolution-independent percentage
 * coordinates, the bounds are applied via the per-bucket `device_type`
 * dimension rather than raw viewport pixels (clickhouse.md §5.1).
 */
final readonly class HeatmapQuery
{
    public function __construct(
        public Uuid $siteId,
        public DateRange $dateRange,
        public string $pathname,
        public HeatmapType $type,
        public ?int $viewportWidthMin,
        public ?int $viewportWidthMax,
    ) {
    }
}
