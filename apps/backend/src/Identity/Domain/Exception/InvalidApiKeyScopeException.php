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

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\FieldError;

final class InvalidApiKeyScopeException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    /**
     * @param list<string> $allowed
     */
    public static function forValue(string $value, array $allowed): self
    {
        $message = sprintf(
            '"%s" is not a valid API key scope. Allowed: %s.',
            $value,
            implode(', ', $allowed),
        );

        return new self($message, [new FieldError('scopes', 'invalid_enum_value', $message)]);
    }

    public static function empty(): self
    {
        $message = 'An API key must declare at least one scope.';

        return new self($message, [new FieldError('scopes', 'required', $message)]);
    }
}
