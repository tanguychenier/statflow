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

namespace App\Reporting\Domain\Model;

use App\Reporting\Domain\Exception\InvalidAlertException;

/**
 * How an alert compares the observed metric to its threshold. Absolute
 * conditions ({@see Above}/{@see Below}) test the raw value; percentage-change
 * conditions test the relative move against a {@see ComparisonPeriod} and so
 * require one to be set.
 */
enum AlertCondition: string
{
    case Above = 'above';
    case Below = 'below';
    case ChangePctAbove = 'change_pct_above';
    case ChangePctBelow = 'change_pct_below';

    public static function fromString(string $value): self
    {
        $condition = self::tryFrom($value);

        if ($condition === null) {
            throw InvalidAlertException::unknownCondition($value);
        }

        return $condition;
    }

    public function requiresComparisonPeriod(): bool
    {
        return $this === self::ChangePctAbove || $this === self::ChangePctBelow;
    }

    /**
     * Whether $observed breaches the rule given its $threshold. For percentage
     * conditions $observed is the already-computed percentage delta.
     */
    public function isBreached(float $observed, float $threshold): bool
    {
        return match ($this) {
            self::Above, self::ChangePctAbove => $observed > $threshold,
            self::Below, self::ChangePctBelow => $observed < $threshold,
        };
    }
}
