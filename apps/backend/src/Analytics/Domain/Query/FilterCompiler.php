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

namespace App\Analytics\Domain\Query;

use App\Analytics\Domain\Exception\InvalidFilter;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;

/**
 * Compiles a {@see FilterSet} (and any number of AND-ed sets) into a single
 * parameterised SQL WHERE fragment.
 *
 * Identifiers (column names) come exclusively from the {@see Dimension} enum;
 * every operand is bound through the {@see ParameterBag}. There is no path by
 * which user input reaches the SQL string unescaped, which is the whole point
 * of the closed-dimension design.
 */
final readonly class FilterCompiler
{
    public function __construct(
        private ParameterBag $parameters,
    ) {
    }

    /**
     * Compile several filter sets joined by a top-level AND. Empty sets are
     * skipped; an entirely empty input yields `1 = 1`.
     */
    public function compileGroups(Grain $grain, FilterSet ...$groups): BoundExpression
    {
        $rendered = [];
        foreach ($groups as $group) {
            if ($group->isEmpty()) {
                continue;
            }
            $rendered[] = '(' . $this->compileSet($grain, $group) . ')';
        }

        if ($rendered === []) {
            return new BoundExpression('1 = 1', $this->parameters->all());
        }

        return new BoundExpression(implode(' AND ', $rendered), $this->parameters->all());
    }

    private function compileSet(Grain $grain, FilterSet $set): string
    {
        $predicates = array_map(
            fn (Filter $filter): string => $this->compileFilter($grain, $filter),
            $set->filters,
        );

        return implode(' ' . $set->combination->sqlKeyword() . ' ', $predicates);
    }

    private function compileFilter(Grain $grain, Filter $filter): string
    {
        $column = $this->column($grain, $filter->dimension);

        return match ($filter->operator) {
            FilterOperator::Eq => sprintf('%s = %s', $column, $this->scalar($filter)),
            FilterOperator::Neq => sprintf('%s != %s', $column, $this->scalar($filter)),
            FilterOperator::In => sprintf('%s IN %s', $column, $this->list($filter)),
            FilterOperator::NotIn => sprintf('%s NOT IN %s', $column, $this->list($filter)),
            FilterOperator::Contains => sprintf('positionCaseInsensitive(%s, %s) > 0', $column, $this->scalar($filter)),
            FilterOperator::NotContains => sprintf('positionCaseInsensitive(%s, %s) = 0', $column, $this->scalar($filter)),
            FilterOperator::StartsWith => sprintf('startsWith(%s, %s)', $column, $this->scalar($filter)),
            FilterOperator::Gt => sprintf('%s > %s', $column, $this->scalar($filter)),
            FilterOperator::Gte => sprintf('%s >= %s', $column, $this->scalar($filter)),
            FilterOperator::Lt => sprintf('%s < %s', $column, $this->scalar($filter)),
            FilterOperator::Lte => sprintf('%s <= %s', $column, $this->scalar($filter)),
        };
    }

    private function column(Grain $grain, Dimension $dimension): string
    {
        if ($grain === Grain::Sessions) {
            return $dimension->sessionColumn();
        }

        if ($dimension->isSessionScoped()) {
            throw InvalidFilter::unknownOperator(sprintf(
                'dimension "%s" is only available on session-grained queries',
                $dimension->value,
            ));
        }

        return $dimension->eventColumn();
    }

    private function scalar(Filter $filter): string
    {
        $value = $filter->scalarValue();

        if (is_int($value)) {
            return $this->parameters->bind('Int64', $value);
        }
        if (is_float($value)) {
            return $this->parameters->bind('Float64', $value);
        }
        if (is_bool($value)) {
            return $this->parameters->bind('UInt8', $value ? 1 : 0);
        }

        return $this->parameters->bind('String', $value);
    }

    private function list(Filter $filter): string
    {
        return $this->parameters->bind('Array(String)', $filter->listValue());
    }
}
