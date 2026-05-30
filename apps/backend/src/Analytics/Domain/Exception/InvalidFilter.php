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

namespace App\Analytics\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ErrorType;

final class InvalidFilter extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::InvalidFilter;
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('Filter is missing required field "%s".', $field));
    }

    public static function unknownOperator(string $operator): self
    {
        return new self(sprintf('"%s" is not a supported filter operator.', $operator));
    }

    public static function unknownCombination(string $combination): self
    {
        return new self(sprintf('"%s" is not a valid filter combination (expected "and" or "or").', $combination));
    }

    public static function operatorArityMismatch(string $operator): self
    {
        return new self(sprintf('Operator "%s" was given a value of the wrong arity.', $operator));
    }

    public static function invalidValueType(string $operator): self
    {
        return new self(sprintf('Operator "%s" received a value of an unsupported type.', $operator));
    }
}
