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
 * Raised when an accepted member lacks the role required for a mutation
 * (error catalog: permission-denied, HTTP 403). The detail names the missing
 * capability.
 */
final class PermissionDeniedException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::PermissionDenied;
    }

    public static function requires(string $capability): self
    {
        return new self(sprintf('This action requires the "%s" capability.', $capability));
    }
}
