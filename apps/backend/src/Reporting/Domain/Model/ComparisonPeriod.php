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

namespace App\Reporting\Domain\Model;

use App\Reporting\Domain\Exception\InvalidAlertException;

/**
 * Reference window a percentage-change alert compares the current period against.
 * Mirrors the OpenAPI `Alert.comparison_period` enum.
 */
enum ComparisonPeriod: string
{
    case PreviousDay = 'previous_day';
    case PreviousWeek = 'previous_week';
    case PreviousMonth = 'previous_month';

    public static function fromString(string $value): self
    {
        $period = self::tryFrom($value);

        if ($period === null) {
            throw InvalidAlertException::unknownComparisonPeriod($value);
        }

        return $period;
    }
}
