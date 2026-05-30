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

namespace App\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidRetentionException;

/**
 * Per-site raw-event retention window, in days (30–730).
 *
 * A NULL retention on a site means "inherit the global 365-day default"; that
 * absence is represented by a NULL column, not by this object. When present the
 * range is enforced to match both the PostgreSQL CHECK and the API bounds.
 */
final readonly class RetentionDays
{
    public const MIN = 30;

    public const MAX = 730;

    public const DEFAULT = 365;

    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidRetentionException::outOfRange($value, self::MIN, self::MAX);
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
