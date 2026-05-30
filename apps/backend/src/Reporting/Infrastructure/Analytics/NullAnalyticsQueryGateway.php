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

namespace App\Reporting\Infrastructure\Analytics;

use App\Reporting\Domain\Port\AnalyticsQueryGateway;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Inert {@see AnalyticsQueryGateway} used when the Analytics read side is not
 * wired in (e.g. a minimal install or during isolated Reporting tests).
 *
 * Exports produce an empty file rather than failing, and alerts never breach
 * (no data is observed). It exists so the Reporting context boots and degrades
 * gracefully; the wiring agent swaps in the real bus-backed adapter in
 * production.
 */
final class NullAnalyticsQueryGateway implements AnalyticsQueryGateway
{
    public function fetchRows(Uuid $siteId, string $reportType, array $query): array
    {
        return [];
    }

    public function evaluateMetric(
        Uuid $siteId,
        string $metric,
        array $filters,
        ?string $comparisonPeriod,
    ): array {
        return [
            'current' => null,
            'baseline' => null,
        ];
    }
}
