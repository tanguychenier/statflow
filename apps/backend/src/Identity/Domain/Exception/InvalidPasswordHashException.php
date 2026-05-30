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

final class InvalidPasswordHashException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::InternalError;
    }

    public static function empty(): self
    {
        return new self('Password hash must not be empty.');
    }
}
