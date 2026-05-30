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

use App\Reporting\Domain\Exception\InvalidReportNameException;

/**
 * Optional free-text description of a saved report, bounded to the OpenAPI
 * `description` constraint (max 1000 chars). An empty/blank input is normalised
 * to "no description" (null) rather than an empty string.
 */
final readonly class ReportDescription implements \Stringable
{
    public const MAX_LENGTH = 1000;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromNullableString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidReportNameException::descriptionTooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
