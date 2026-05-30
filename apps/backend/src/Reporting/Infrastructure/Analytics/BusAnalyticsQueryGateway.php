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

use App\Reporting\Application\Query\Analytics\EvaluateMetricQuery;
use App\Reporting\Application\Query\Analytics\FetchExportRowsQuery;
use App\Reporting\Application\Query\Analytics\MetricReading;
use App\Reporting\Application\Query\Analytics\TabularRows;
use App\Reporting\Domain\Port\AnalyticsQueryGateway;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * {@see AnalyticsQueryGateway} adapter that reads analytical data via the
 * `query.bus` (architecture.md "Context interaction rules"): it dispatches the
 * Reporting-owned cross-context queries, which Analytics-side handlers answer.
 * Reporting never touches ClickHouse or the Analytics domain directly.
 */
final readonly class BusAnalyticsQueryGateway implements AnalyticsQueryGateway
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function fetchRows(Uuid $siteId, string $reportType, array $query): array
    {
        $result = $this->queryBus->ask(new FetchExportRowsQuery(
            $siteId->getValue(),
            $reportType,
            $query,
        ));

        return $result instanceof TabularRows ? $result->rows : [];
    }

    public function evaluateMetric(
        Uuid $siteId,
        string $metric,
        array $filters,
        ?string $comparisonPeriod,
    ): array {
        $result = $this->queryBus->ask(new EvaluateMetricQuery(
            $siteId->getValue(),
            $metric,
            $filters,
            $comparisonPeriod,
        ));

        if ($result instanceof MetricReading) {
            return [
                'current' => $result->current,
                'baseline' => $result->baseline,
            ];
        }

        return [
            'current' => null,
            'baseline' => null,
        ];
    }
}
