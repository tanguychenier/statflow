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

use App\Sites\Domain\Exception\InvalidSamplingRateException;

/**
 * Per-session client-side sampling rate (0.000–1.000).
 *
 * 1.0 tracks every session, 0.0 tracks none. The PostgreSQL column is
 * NUMERIC(4,3): three decimal places. We round to that precision on
 * construction so the stored and in-memory values can never diverge.
 */
final readonly class SamplingRate
{
    public const MIN = 0.0;

    public const MAX = 1.0;

    public const PRECISION = 3;

    public const DEFAULT = 1.0;

    private float $value;

    private function __construct(float $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidSamplingRateException::outOfRange($value);
        }

        $this->value = round($value, self::PRECISION);
    }

    public static function fromFloat(float $value): self
    {
        return new self($value);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function value(): float
    {
        return $this->value;
    }
}
