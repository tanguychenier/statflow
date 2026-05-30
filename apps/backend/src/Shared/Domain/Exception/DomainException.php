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

use RuntimeException;
use Throwable;

/**
 * Base class for every business-rule violation.
 *
 * A domain exception is self-describing so the HTTP layer can render an RFC 9457
 * Problem Details response without a translation table. Subclasses bind to a
 * canonical {@see ErrorType} by overriding {@see errorType()}; the type URI,
 * title, and HTTP status are then derived automatically.
 *
 * Subclasses MAY instead override {@see getType()} directly when they need a
 * bespoke URI; in that case the title/status defaults still come from
 * {@see errorType()}, so such subclasses should override those too if the
 * default ({@see ErrorType::ValidationFailed}) is not appropriate.
 *
 * Callers may attach {@see FieldError}s for 422 responses via the constructor.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param list<FieldError> $errors
     */
    public function __construct(
        string $message = '',
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The canonical error type this exception maps to. Defaults to a 422
     * validation failure; subclasses bound to a different type override this.
     */
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    /**
     * Dereferenceable error type URI (RFC 9457 `type`).
     */
    public function getType(): string
    {
        return $this->errorType()->uri();
    }

    public function getTitle(): string
    {
        return $this->errorType()->title();
    }

    public function getStatusCode(): int
    {
        return $this->errorType()->status();
    }

    /**
     * @return list<FieldError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
