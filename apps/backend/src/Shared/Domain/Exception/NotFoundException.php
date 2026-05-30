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
 * Raised when a requested resource does not exist or is not visible to the
 * caller (HTTP 404). The same response is returned for "forbidden" resources to
 * avoid existence disclosure — see `docs/api/error-catalog.md`.
 */
final class NotFoundException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function of(string $resource, string $identifier): self
    {
        return new self(sprintf('%s "%s" was not found.', $resource, $identifier));
    }
}
