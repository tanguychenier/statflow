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

final class InvalidEmailException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    public static function empty(): self
    {
        return self::forMessage('Email address must not be empty.');
    }

    public static function tooLong(int $max): self
    {
        $message = sprintf('Email address must not exceed %d characters.', $max);

        return new self($message, [new FieldError('email', 'max_length_exceeded', $message)]);
    }

    public static function malformed(string $value): self
    {
        return self::forMessage(sprintf('"%s" is not a valid email address.', $value));
    }

    private static function forMessage(string $message): self
    {
        return new self($message, [new FieldError('email', 'invalid_format', $message)]);
    }
}
