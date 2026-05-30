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

use App\Shared\Domain\Bus\Query\Query;

/**
 * Cross-context read dispatched on the `query.bus` to evaluate a single alert
 * metric (and its baseline, for percentage conditions) in the Analytics context.
 * Owned by Reporting; answered by an Analytics-side handler.
 */
final readonly class EvaluateMetricQuery implements Query
{
    /**
     * @param array<string, mixed> $filters opaque Analytics filter set
     */
    public function __construct(
        public string $siteId,
        public string $metric,
        public array $filters,
        public ?string $comparisonPeriod,
    ) {
    }
}
