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

namespace App\Ingestion\Domain\Model;

/**
 * Outcome of a rate-limit check: whether the request is allowed and, when it is
 * not, how many seconds the caller should wait (surfaced as `Retry-After`).
 */
final readonly class RateLimitDecision
{
    private function __construct(
        public bool $allowed,
        public int $retryAfterSeconds,
    ) {
    }

    public static function allowed(): self
    {
        return new self(true, 0);
    }

    public static function denied(int $retryAfterSeconds): self
    {
        return new self(false, max(1, $retryAfterSeconds));
    }
}
