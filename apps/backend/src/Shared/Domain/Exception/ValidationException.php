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

namespace App\Shared\Domain\Exception;

use Throwable;

/**
 * Raised when a syntactically valid payload fails business validation (HTTP 422).
 * Carries the per-field {@see FieldError}s rendered in the `errors[]` array.
 */
final class ValidationException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    /**
     * @param list<FieldError> $errors
     */
    public static function withErrors(array $errors, string $message = 'The request payload failed validation.'): self
    {
        return new self($message, $errors);
    }

    public static function forField(
        string $field,
        string $code,
        string $message,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf('%s: %s', $field, $message),
            [new FieldError($field, $code, $message)],
            $previous,
        );
    }
}
