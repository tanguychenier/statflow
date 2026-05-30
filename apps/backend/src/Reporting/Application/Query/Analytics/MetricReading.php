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

namespace App\Reporting\Application\Query\Analytics;

use App\Shared\Domain\Bus\Query\QueryResult;

/**
 * Result of an {@see EvaluateMetricQuery}: the metric's current value and, when a
 * comparison period was requested, its baseline. Either may be null when no data
 * exists for the window.
 */
final readonly class MetricReading implements QueryResult
{
    public function __construct(
        public ?float $current,
        public ?float $baseline,
    ) {
    }
}
