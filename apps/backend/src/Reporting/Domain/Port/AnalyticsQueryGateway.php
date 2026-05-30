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

namespace App\Reporting\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port through which Reporting reads analytical data owned by the
 * Analytics context. The adapter dispatches the matching Analytics query on the
 * query bus (architecture.md "Context interaction rules"); Reporting never
 * touches ClickHouse or the Analytics domain directly.
 */
interface AnalyticsQueryGateway
{
    /**
     * Run a saved/export query for a site and return its result rows as a list
     * of associative arrays (column => scalar), suitable for tabular export.
     *
     * @param array<string, mixed> $query  serialised Analytics query definition
     *
     * @return list<array<string, scalar|null>>
     */
    public function fetchRows(Uuid $siteId, string $reportType, array $query): array;

    /**
     * Evaluate a single scalar metric for an alert over the current period, and
     * (when needed) the comparison period. Returns the current value, or null
     * when no data exists for the window.
     *
     * @param array<string, mixed>        $filters          opaque Analytics filter set
     * @param non-empty-string            $metric
     * @param non-empty-string|null       $comparisonPeriod
     *
     * @return array{current: float|null, baseline: float|null}
     */
    public function evaluateMetric(
        Uuid $siteId,
        string $metric,
        array $filters,
        ?string $comparisonPeriod,
    ): array;
}
