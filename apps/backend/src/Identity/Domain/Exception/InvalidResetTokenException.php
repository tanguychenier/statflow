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

/**
 * Raised when a password-reset token is unknown, expired, or already consumed
 * (openapi.yaml /auth/reset-password 401). The closed error catalog has no
 * dedicated reset-token type, so this maps to the nearest 401 contract,
 * token-revoked, which accurately conveys "this token is no longer usable".
 */
final class InvalidResetTokenException extends IdentityException
{
    public function __construct()
    {
        parent::__construct('The password reset token is invalid, expired, or has already been used.');
    }

    public function errorType(): ErrorType
    {
        return ErrorType::TokenRevoked;
    }
}
