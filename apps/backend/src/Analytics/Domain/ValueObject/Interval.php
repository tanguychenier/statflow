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
 * Time-series bucket granularity (OpenAPI `interval`).
 */
enum Interval: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownInterval::forName($value);
    }

    /**
     * Pick a sensible bucket size for a span so series stay readable and the
     * query stays cheap: sub-day spans bucket by hour, multi-month by week, etc.
     */
    public static function autoSelect(int $inclusiveDayCount): self
    {
        return match (true) {
            $inclusiveDayCount <= 1 => self::Hour,
            $inclusiveDayCount <= 60 => self::Day,
            $inclusiveDayCount <= 365 => self::Week,
            default => self::Month,
        };
    }

    /**
     * The ClickHouse `toStartOf*` function that snaps a timestamp to this bucket.
     */
    public function clickHouseBucketFunction(): string
    {
        return match ($this) {
            self::Minute => 'toStartOfMinute',
            self::Hour => 'toStartOfHour',
            self::Day => 'toStartOfDay',
            self::Week => 'toStartOfWeek',
            self::Month => 'toStartOfMonth',
        };
    }
}
