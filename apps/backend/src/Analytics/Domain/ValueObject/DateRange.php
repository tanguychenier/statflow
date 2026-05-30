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

namespace App\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\InvalidDateRange;
use DateTimeImmutable;

/**
 * An inclusive `YYYY-MM-DD`..`YYYY-MM-DD` query window.
 *
 * The window is stored as two calendar dates and resolved to a half-open UTC
 * instant range `[startOfDay(from), startOfDay(to)+1day)` when handed to
 * ClickHouse, so an inclusive `date_to` covers the whole final day.
 */
final readonly class DateRange
{
    /**
     * Hard upper bound on the span a single query may scan, keeping dashboard
     * latency bounded and preventing accidental full-table scans.
     */
    public const MAX_SPAN_DAYS = 730;

    private function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
    }

    public static function fromStrings(string $from, string $to): self
    {
        $start = self::parseDate($from, 'date_from');
        $end = self::parseDate($to, 'date_to');

        if ($start > $end) {
            throw InvalidDateRange::orderInverted();
        }

        $spanDays = (int) $start->diff($end)->format('%a');
        if ($spanDays > self::MAX_SPAN_DAYS) {
            throw InvalidDateRange::tooLong(self::MAX_SPAN_DAYS);
        }

        return new self($start, $end);
    }

    /**
     * Number of whole days covered, inclusive of both endpoints.
     */
    public function inclusiveDayCount(): int
    {
        return (int) $this->from->diff($this->to)->format('%a') + 1;
    }

    /**
     * The exclusive upper instant for half-open ClickHouse range filters:
     * the start of the day after `to`.
     */
    public function exclusiveEnd(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    public function startInstant(): DateTimeImmutable
    {
        return $this->from;
    }

    /**
     * `YYYY-MM-DD` for the start-of-day UTC instant of `from`.
     */
    public function fromDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    /**
     * `YYYY-MM-DD` for the start-of-day UTC instant of `to`.
     */
    public function toDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    /**
     * Same-length window immediately preceding this one (for `previous_period`).
     */
    public function previousPeriod(): self
    {
        $days = $this->inclusiveDayCount();
        $newTo = $this->from->modify('-1 day');
        $newFrom = $newTo->modify(sprintf('-%d days', $days - 1));

        return new self($newFrom, $newTo);
    }

    /**
     * Same calendar window one year earlier (for `previous_year`).
     */
    public function previousYear(): self
    {
        return new self(
            $this->from->modify('-1 year'),
            $this->to->modify('-1 year'),
        );
    }

    private static function parseDate(string $value, string $field): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw InvalidDateRange::malformed($field, $value);
        }

        return $parsed;
    }
}
