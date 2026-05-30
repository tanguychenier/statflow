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

use App\Analytics\Domain\Exception\InvalidFilter;
use App\Analytics\Domain\Exception\MissingQueryField;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Domain\ValueObject\HeatmapType;
use App\Analytics\Domain\ValueObject\Interval;
use App\Analytics\Domain\ValueObject\Metric;
use App\Analytics\Domain\ValueObject\RetentionInterval;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Translates decoded request bodies (OpenAPI request schemas) into the
 * Application's strongly-typed query objects, performing all structural
 * validation in one place so handlers and controllers stay declarative.
 */
final class AnalyticsQueryFactory
{
    /**
     * @param array<string, mixed> $body
     */
    public function analyticsQuery(Uuid $siteId, array $body): AnalyticsQuery
    {
        return new AnalyticsQuery(
            $siteId,
            $this->dateRange($body),
            $this->filterSet($body),
            $this->optionalUuid($body['segment_id'] ?? null),
            $this->comparePeriod($body['compare_period'] ?? null),
            $this->metrics($body['metrics'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public function timeSeriesQuery(Uuid $siteId, array $body): TimeSeriesQuery
    {
        $interval = isset($body['interval']) && is_string($body['interval'])
            ? Interval::fromString($body['interval'])
            : null;

        return new TimeSeriesQuery($this->analyticsQuery($siteId, $body), $interval);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function breakdownQuery(Uuid $siteId, array $body): BreakdownQuery
    {
        $property = $this->requireString($body, 'property');
        $dimension = Dimension::fromString($property);

        $sortBy = isset($body['sort_by']) && is_string($body['sort_by'])
            ? Metric::fromString($body['sort_by'])
            : Metric::Visitors;

        $sortOrder = is_string($body['sort_order'] ?? null) ? $body['sort_order'] : 'desc';
        $limit = $this->boundedInt($body['limit'] ?? 100, 1, BreakdownQuery::MAX_LIMIT, 100);

        return new BreakdownQuery(
            $this->analyticsQuery($siteId, $body),
            $dimension,
            $sortBy,
            $sortOrder !== 'asc',
            $limit,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public function funnelQuery(Uuid $siteId, array $body): FunnelQuery
    {
        $funnelId = Uuid::fromString($this->requireString($body, 'funnel_id'));
        $window = $this->boundedInt($body['conversion_window_days'] ?? 30, 1, 90, 30);

        return new FunnelQuery(
            $siteId,
            $funnelId,
            $this->dateRange($body),
            $window,
            $this->filterSet($body),
            $this->optionalUuid($body['segment_id'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public function retentionQuery(Uuid $siteId, array $body): RetentionQuery
    {
        $interval = is_string($body['interval'] ?? null)
            ? RetentionInterval::fromString($body['interval'])
            : RetentionInterval::Week;

        $returnEvent = is_string($body['return_event'] ?? null) && $body['return_event'] !== ''
            ? $body['return_event']
            : 'pageview';

        return new RetentionQuery($siteId, $this->dateRange($body), $interval, $returnEvent);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function heatmapQuery(Uuid $siteId, array $body): HeatmapQuery
    {
        $pathname = $this->requireString($body, 'pathname');
        $type = is_string($body['heatmap_type'] ?? null)
            ? HeatmapType::fromString($body['heatmap_type'])
            : HeatmapType::Click;

        return new HeatmapQuery(
            $siteId,
            $this->dateRange($body),
            $pathname,
            $type,
            $this->optionalInt($body['viewport_width_min'] ?? null),
            $this->optionalInt($body['viewport_width_max'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dateRange(array $body): DateRange
    {
        return DateRange::fromStrings(
            $this->requireString($body, 'date_from'),
            $this->requireString($body, 'date_to'),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function filterSet(array $body): FilterSet
    {
        $raw = $body['filters'] ?? [];
        if (!is_array($raw)) {
            throw InvalidFilter::invalidValueType('filters');
        }

        /** @var list<array<string, mixed>> $filters */
        $filters = [];
        foreach (array_values($raw) as $entry) {
            if (!is_array($entry)) {
                throw InvalidFilter::invalidValueType('filters');
            }
            /** @var array<string, mixed> $entry */
            $filters[] = $entry;
        }

        $combination = is_string($body['filter_combination'] ?? null) ? $body['filter_combination'] : 'and';

        return $filters === [] ? FilterSet::empty() : FilterSet::fromArray($filters, $combination);
    }

    /**
     * @return list<Metric>
     */
    private function metrics(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $metrics = [];
        foreach ($raw as $name) {
            if (is_string($name)) {
                $metrics[] = Metric::fromString($name);
            }
        }

        return $metrics;
    }

    private function comparePeriod(mixed $raw): ?string
    {
        if ($raw === AnalyticsQuery::COMPARE_PREVIOUS_PERIOD || $raw === AnalyticsQuery::COMPARE_PREVIOUS_YEAR) {
            return $raw;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw MissingQueryField::named($key);
        }

        return $value;
    }

    private function optionalUuid(mixed $raw): ?Uuid
    {
        return is_string($raw) && $raw !== '' ? Uuid::fromString($raw) : null;
    }

    private function optionalInt(mixed $raw): ?int
    {
        return is_int($raw) ? $raw : (is_numeric($raw) ? (int) $raw : null);
    }

    private function boundedInt(mixed $raw, int $min, int $max, int $default): int
    {
        $value = is_int($raw) ? $raw : (is_numeric($raw) ? (int) $raw : $default);

        return max($min, min($max, $value));
    }
}
