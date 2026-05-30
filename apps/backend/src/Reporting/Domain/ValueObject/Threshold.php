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

namespace App\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidAlertException;

/**
 * The numeric boundary an alert tests its metric against.
 *
 * Stored to match the `alerts.threshold NUMERIC(18,4)` column: finite, within
 * the column's magnitude, and rounded to four decimal places so the persisted
 * value round-trips exactly.
 */
final readonly class Threshold
{
    public const SCALE = 4;

    public const MAX_ABS = 99_999_999_999_999.9999;

    private function __construct(
        private float $value,
    ) {
    }

    public static function fromFloat(float $value): self
    {
        if (!is_finite($value)) {
            throw InvalidAlertException::nonFiniteThreshold();
        }

        $rounded = round($value, self::SCALE);

        if (abs($rounded) > self::MAX_ABS) {
            throw InvalidAlertException::thresholdOutOfRange();
        }

        return new self($rounded);
    }

    public function value(): float
    {
        return $this->value;
    }
}
