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

/**
 * Raised when a request lacks a valid authentication credential (HTTP 401).
 */
final class AuthenticationRequiredException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::AuthenticationRequired;
    }
}
