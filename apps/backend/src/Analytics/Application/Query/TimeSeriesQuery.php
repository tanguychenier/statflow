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

use App\Analytics\Domain\ValueObject\Interval;

/**
 * An {@see AnalyticsQuery} plus an optional explicit bucket interval
 * (OpenAPI `getTimeSeries`). When the interval is null it is auto-selected from
 * the date span.
 */
final readonly class TimeSeriesQuery
{
    public function __construct(
        public AnalyticsQuery $query,
        public ?Interval $interval,
    ) {
    }

    public function resolvedInterval(): Interval
    {
        return $this->interval ?? Interval::autoSelect($this->query->dateRange->inclusiveDayCount());
    }
}
