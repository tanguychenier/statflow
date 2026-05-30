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

use App\Analytics\Domain\Exception\UnknownInterval;

/**
 * Cohort granularity for retention analysis (OpenAPI `RetentionQueryRequest.interval`).
 */
enum RetentionInterval: string
{
    case Week = 'week';
    case Month = 'month';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownInterval::forName($value);
    }

    /**
     * The ClickHouse `toStartOf*` function that snaps a timestamp to a cohort.
     */
    public function clickHouseBucketFunction(): string
    {
        return $this === self::Week ? 'toStartOfWeek' : 'toStartOfMonth';
    }

    /**
     * The ClickHouse `INTERVAL` unit used to step from cohort start to an offset.
     */
    public function intervalUnit(): string
    {
        return $this === self::Week ? 'WEEK' : 'MONTH';
    }
}
