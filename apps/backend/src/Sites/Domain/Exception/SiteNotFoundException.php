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
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Raised when a site cannot be resolved. Mapped to HTTP 404. The same response
 * is also used for sites the caller is not permitted to see, to avoid leaking
 * the existence of resources across teams (error-catalog `not-found`).
 */
final class SiteNotFoundException extends SitesDomainException
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
