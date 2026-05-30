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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Port\AnalyticsQueryGateway;
use App\Shared\Domain\ValueObject\Uuid;
use RuntimeException;

/**
 * Configurable {@see AnalyticsQueryGateway} double for export and alert tests.
 */
final class FakeAnalyticsQueryGateway implements AnalyticsQueryGateway
{
    /**
     * @var list<array<string, scalar|null>>
     */
    private array $rows = [];

    private ?float $current = null;

    private ?float $baseline = null;

    private bool $throwOnFetch = false;

    private bool $throwOnEvaluate = false;

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    public function withRows(array $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function withReading(?float $current, ?float $baseline = null): self
    {
        $this->current = $current;
        $this->baseline = $baseline;

        return $this;
    }

    public function failOnFetch(): self
    {
        $this->throwOnFetch = true;

        return $this;
    }

    public function failOnEvaluate(): self
    {
        $this->throwOnEvaluate = true;

        return $this;
    }

    public function fetchRows(Uuid $siteId, string $reportType, array $query): array
    {
        if ($this->throwOnFetch) {
            throw new RuntimeException('analytics fetch failed');
        }

        return $this->rows;
    }

    public function evaluateMetric(Uuid $siteId, string $metric, array $filters, ?string $comparisonPeriod): array
    {
        if ($this->throwOnEvaluate) {
            throw new RuntimeException('analytics evaluate failed');
        }

        return [
            'current' => $this->current,
            'baseline' => $this->baseline,
        ];
    }
}
