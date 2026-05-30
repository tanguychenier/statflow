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

namespace App\Reporting\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;

/**
 * Raised when the authenticated user belongs to the site's team but lacks the
 * role required for the requested reporting mutation (error-catalog
 * `permission-denied`, HTTP 403). The detail names the missing capability.
 */
final class PermissionDeniedException extends ReportingDomainException
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
