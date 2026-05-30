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

namespace App\Analytics\Application\Query;

use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\Metric;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * The shared shape of every analytics read query (OpenAPI `AnalyticsQueryBase`):
 * a site, a date window, inline filters, an optional saved segment, an optional
 * comparison period, and the metrics to compute.
 *
 * Immutable application-layer carrier; validation happened while parsing.
 */
final readonly class AnalyticsQuery
{
    public const COMPARE_PREVIOUS_PERIOD = 'previous_period';

    public const COMPARE_PREVIOUS_YEAR = 'previous_year';

    /**
     * @param list<Metric> $metrics
     */
    public function __construct(
        public Uuid $siteId,
        public DateRange $dateRange,
        public FilterSet $inlineFilters,
        public ?Uuid $segmentId,
        public ?string $comparePeriod,
        public array $metrics,
    ) {
    }

    public function comparisonRange(): ?DateRange
    {
        return match ($this->comparePeriod) {
            self::COMPARE_PREVIOUS_PERIOD => $this->dateRange->previousPeriod(),
            self::COMPARE_PREVIOUS_YEAR => $this->dateRange->previousYear(),
            default => null,
        };
    }

    public function withDateRange(DateRange $range): self
    {
        return new self(
            $this->siteId,
            $range,
            $this->inlineFilters,
            $this->segmentId,
            null,
            $this->metrics,
        );
    }
}
