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
 * A human-friendly name for a report, scheduled report or alert.
 *
 * Bounded to the OpenAPI `name` constraint (1..200 chars after trimming). The
 * raw value is stored as-is once trimmed; presentation concerns (escaping) are
 * the HTTP layer's, not the domain's.
 */
final readonly class ReportName implements \Stringable
{
    public const MAX_LENGTH = 200;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidReportNameException::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidReportNameException::tooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
