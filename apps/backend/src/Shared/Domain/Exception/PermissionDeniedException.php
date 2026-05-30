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
 * Raised when an authenticated identity lacks the required role or scope for an
 * operation (HTTP 403). The `detail` SHOULD name the missing permission.
 */
final class PermissionDeniedException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::PermissionDenied;
    }

    public static function requiring(string $permission): self
    {
        return new self(sprintf('This operation requires the "%s" permission.', $permission));
    }
}
