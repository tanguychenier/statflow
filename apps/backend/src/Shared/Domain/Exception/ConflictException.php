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
 * Raised when a resource with conflicting unique attributes already exists
 * (HTTP 409) — e.g. a duplicate site domain or team name.
 */
final class ConflictException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::Conflict;
    }

    public static function of(string $attribute, string $value): self
    {
        return new self(sprintf('A resource with %s "%s" already exists.', $attribute, $value));
    }
}
