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

use App\Analytics\Domain\Exception\InvalidFilter;

/**
 * A single `(dimension operator value)` predicate (OpenAPI `Filter`).
 *
 * The value is normalised to a scalar or a list of scalars and validated
 * against the operator's arity at construction time, so downstream query
 * builders never re-validate.
 */
final readonly class Filter
{
    /**
     * @param string|int|float|bool|list<string|int|float> $value
     */
    private function __construct(
        public Dimension $dimension,
        public FilterOperator $operator,
        public string|int|float|bool|array $value,
    ) {
    }

    public static function create(Dimension $dimension, FilterOperator $operator, mixed $value): self
    {
        $normalised = self::normaliseValue($operator, $value);

        return new self($dimension, $operator, $normalised);
    }

    /**
     * Build from the decoded OpenAPI `Filter` object shape.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        foreach (['property', 'operator', 'value'] as $required) {
            if (!array_key_exists($required, $raw)) {
                throw InvalidFilter::missingField($required);
            }
        }

        if (!is_string($raw['property'])) {
            throw InvalidFilter::missingField('property');
        }

        if (!is_string($raw['operator'])) {
            throw InvalidFilter::unknownOperator(is_scalar($raw['operator']) ? (string) $raw['operator'] : '');
        }

        return self::create(
            Dimension::fromString($raw['property']),
            FilterOperator::fromString($raw['operator']),
            $raw['value'],
        );
    }

    /**
     * @return list<string>
     */
    public function listValue(): array
    {
        if (!is_array($this->value)) {
            throw InvalidFilter::operatorArityMismatch($this->operator->value);
        }

        return array_map(static fn (string|int|float $item): string => (string) $item, $this->value);
    }

    public function scalarValue(): string|int|float|bool
    {
        if (is_array($this->value)) {
            throw InvalidFilter::operatorArityMismatch($this->operator->value);
        }

        return $this->value;
    }

    /**
     * @return string|int|float|bool|list<string|int|float>
     */
    private static function normaliseValue(FilterOperator $operator, mixed $value): string|int|float|bool|array
    {
        if ($operator->expectsList()) {
            if (!is_array($value) || $value === []) {
                throw InvalidFilter::operatorArityMismatch($operator->value);
            }

            $normalised = [];
            foreach (array_values($value) as $item) {
                if (!is_string($item) && !is_int($item) && !is_float($item)) {
                    throw InvalidFilter::invalidValueType($operator->value);
                }
                $normalised[] = $item;
            }

            return $normalised;
        }

        if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
            throw InvalidFilter::invalidValueType($operator->value);
        }

        return $value;
    }
}
