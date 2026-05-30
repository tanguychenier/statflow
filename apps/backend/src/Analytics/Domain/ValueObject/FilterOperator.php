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
 * Comparison operator of a {@see Filter} (OpenAPI `Filter.operator`).
 */
enum FilterOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidFilter::unknownOperator($value);
    }

    /**
     * Whether the operator expects a list operand (`in` / `not_in`).
     */
    public function expectsList(): bool
    {
        return $this === self::In || $this === self::NotIn;
    }
}
