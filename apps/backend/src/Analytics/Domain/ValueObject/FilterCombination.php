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
 * Boolean glue between the predicates of a {@see FilterSet}.
 */
enum FilterCombination: string
{
    case And = 'and';
    case Or = 'or';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidFilter::unknownCombination($value);
    }

    public function sqlKeyword(): string
    {
        return $this === self::And ? 'AND' : 'OR';
    }
}
