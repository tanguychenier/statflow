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
 * The metric an alert watches. Mirrors the OpenAPI `MetricName` enum so an alert
 * rule maps directly onto an Analytics aggregate query.
 */
enum AlertMetric: string
{
    case Visitors = 'visitors';
    case Pageviews = 'pageviews';
    case Sessions = 'sessions';
    case BounceRate = 'bounce_rate';
    case AvgDuration = 'avg_duration';
    case Events = 'events';
    case ConversionRate = 'conversion_rate';

    public static function fromString(string $value): self
    {
        $metric = self::tryFrom($value);

        if ($metric === null) {
            throw InvalidAlertException::unknownMetric($value);
        }

        return $metric;
    }
}
