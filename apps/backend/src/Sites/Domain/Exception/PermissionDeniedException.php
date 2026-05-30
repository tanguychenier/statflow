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

namespace App\Sites\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;

/**
 * Raised when the authenticated identity belongs to the team but lacks the role
 * required for the requested mutation (error-catalog `permission-denied`,
 * HTTP 403). The detail names the capability that was missing.
 */
final class PermissionDeniedException extends SitesDomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::PermissionDenied;
    }

    public static function requires(string $capability): self
    {
        return new self(sprintf('This operation requires the "%s" capability.', $capability));
    }
}
