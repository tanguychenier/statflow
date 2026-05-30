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

use App\Reporting\Domain\Exception\InvalidReportTypeException;

/**
 * The kind of analytical query a saved report wraps. Mirrors the OpenAPI
 * `SavedReport.report_type` enum and the query families exposed by the Analytics
 * context, so a saved report's stored query can be replayed against the matching
 * Analytics endpoint.
 */
enum ReportType: string
{
    case Aggregate = 'aggregate';
    case TimeSeries = 'timeseries';
    case Breakdown = 'breakdown';
    case Funnel = 'funnel';
    case Retention = 'retention';
    case Heatmap = 'heatmap';

    public static function fromString(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type === null) {
            throw InvalidReportTypeException::unknown($value);
        }

        return $type;
    }
}
