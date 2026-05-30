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

/**
 * An ordered list of {@see Filter} predicates joined by one combination.
 *
 * A saved segment and the inline `filters`/`filter_combination` of a query are
 * both expressed as a FilterSet; the query layer merges them with `AND`.
 */
final readonly class FilterSet
{
    /**
     * @param list<Filter> $filters
     */
    private function __construct(
        public array $filters,
        public FilterCombination $combination,
    ) {
    }

    /**
     * @param list<Filter> $filters
     */
    public static function of(array $filters, FilterCombination $combination = FilterCombination::And): self
    {
        return new self(array_values($filters), $combination);
    }

    public static function empty(): self
    {
        return new self([], FilterCombination::And);
    }

    /**
     * Build from the decoded OpenAPI `filters` array and `filter_combination`.
     *
     * @param list<array<string, mixed>> $rawFilters
     */
    public static function fromArray(array $rawFilters, string $combination = 'and'): self
    {
        $filters = array_map(
            Filter::fromArray(...),
            array_values($rawFilters),
        );

        return new self($filters, FilterCombination::fromString($combination));
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    /**
     * Combine two sets with a top-level `AND` (segment ∧ inline filters).
     *
     * If either side is empty the other is returned verbatim, preserving its
     * own combination; otherwise the merged set wraps both as AND-ed groups.
     *
     * @return list<FilterSet> the groups to AND together
     */
    public function andGroups(self $other): array
    {
        $groups = [];
        if (!$this->isEmpty()) {
            $groups[] = $this;
        }
        if (!$other->isEmpty()) {
            $groups[] = $other;
        }

        return $groups;
    }
}
