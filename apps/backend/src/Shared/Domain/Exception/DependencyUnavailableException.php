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

use Throwable;

/**
 * Raised when a required upstream dependency (ClickHouse, PostgreSQL, Redis) is
 * unreachable (HTTP 503). The HTTP layer adds `Retry-After: 30`.
 */
final class DependencyUnavailableException extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::DependencyUnavailable;
    }

    public static function named(string $dependency, ?Throwable $previous = null): self
    {
        return new self(sprintf('The "%s" dependency is currently unavailable.', $dependency), [], $previous);
    }
}
