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

namespace App\Shared\Infrastructure\Validation;

use App\Shared\Domain\Exception\FieldError;
use App\Shared\Domain\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Translates Symfony validator violations into the Statflow field-error contract.
 *
 * The mapping keeps the framework's validation engine in the infrastructure layer
 * while emitting the domain's {@see ValidationException} so the rest of the stack
 * stays framework-agnostic.
 */
final class ConstraintViolationConverter
{
    public function toException(ConstraintViolationListInterface $violations): ValidationException
    {
        return ValidationException::withErrors($this->toFieldErrors($violations));
    }

    /**
     * @return list<FieldError>
     */
    public function toFieldErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = new FieldError(
                $this->propertyPathToField($violation->getPropertyPath()),
                $this->resolveCode($violation),
                (string) $violation->getMessage(),
            );
        }

        return $errors;
    }

    private function resolveCode(ConstraintViolationInterface $violation): string
    {
        $code = $violation->getCode();

        // The validator's machine codes are UUIDs; the API contract uses
        // snake_case slugs. Absent a mapping we default to the generic slug.
        return $code !== null && !$this->looksLikeUuid($code) ? $code : 'invalid_value';
    }

    private function propertyPathToField(string $propertyPath): string
    {
        // Strip the leading array/object accessor noise Symfony adds, e.g.
        // `[data_retention_days]` → `data_retention_days`.
        return trim($propertyPath, '[]');
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
