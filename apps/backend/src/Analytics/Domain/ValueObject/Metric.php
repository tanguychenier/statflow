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

use App\Analytics\Domain\Exception\UnknownMetric;

/**
 * The closed set of metrics the Stats API can compute (OpenAPI `MetricName`).
 */
enum Metric: string
{
    case Visitors = 'visitors';
    case Pageviews = 'pageviews';
    case Sessions = 'sessions';
    case BounceRate = 'bounce_rate';
    case AvgDuration = 'avg_duration';
    case Events = 'events';
    case ConversionRate = 'conversion_rate';

    /**
     * The metrics computed when a query omits an explicit `metrics` list.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [
            self::Visitors,
            self::Pageviews,
            self::Sessions,
            self::BounceRate,
            self::AvgDuration,
        ];
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownMetric::forName($value);
    }

    /**
     * Whether the metric is derived from the `sessions` table rather than the
     * raw `events` stream. Bounce rate and average duration are session-grained.
     */
    public function isSessionScoped(): bool
    {
        return $this === self::BounceRate || $this === self::AvgDuration;
    }
}
