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

final class WeakPasswordException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    public static function tooShort(int $minLength): self
    {
        $message = sprintf('Password must be at least %d characters long.', $minLength);

        return new self($message, [new FieldError('password', 'min_length_not_met', $message)]);
    }

    public static function tooLong(int $maxLength): self
    {
        $message = sprintf('Password must not exceed %d characters.', $maxLength);

        return new self($message, [new FieldError('password', 'max_length_exceeded', $message)]);
    }
}
