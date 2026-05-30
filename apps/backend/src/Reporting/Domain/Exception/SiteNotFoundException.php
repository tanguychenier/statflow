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
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Raised when the target site cannot be resolved, or when the caller is not a
 * member of its team. Reported as 404 (not 403) so the API never discloses the
 * existence of a site in a team the user cannot see (error-catalog `not-found`).
 */
final class SiteNotFoundException extends ReportingDomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Site "%s" does not exist.', $id->getValue()));
    }
}
