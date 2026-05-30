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

namespace App\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\UnknownDimension;

/**
 * Heatmap rendering mode (OpenAPI `HeatmapQueryRequest.heatmap_type`).
 *
 * `Click` reads the `heatmap_stats` grid; `Scroll` reads `scroll_depth_stats`.
 */
enum HeatmapType: string
{
    case Click = 'click';
    case Scroll = 'scroll';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownDimension::forName('heatmap_type:' . $value);
    }
}
