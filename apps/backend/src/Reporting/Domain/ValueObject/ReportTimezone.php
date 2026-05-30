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

use App\Reporting\Domain\Exception\InvalidScheduleException;
use DateTimeZone;

/**
 * IANA timezone the scheduled-report cron expression is interpreted in.
 *
 * Validated against PHP's compiled IANA database, so the scheduler can always
 * construct a {@see DateTimeZone} when computing the next send time. Mirrors the
 * Sites context's timezone rules without importing its value object (Deptrac).
 */
final readonly class ReportTimezone implements \Stringable
{
    public const DEFAULT = 'UTC';

    private const MAX_LENGTH = 64;

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

        if ($trimmed === '' || strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidScheduleException::unknownTimezone($trimmed);
        }

        if (!in_array($trimmed, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidScheduleException::unknownTimezone($trimmed);
        }

        return new self($trimmed);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->value);
    }
}
