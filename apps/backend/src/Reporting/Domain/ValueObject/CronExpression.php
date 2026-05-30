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
use DateTimeImmutable;
use DateTimeZone;

/**
 * A standard 5-field cron expression (minute hour day-of-month month
 * day-of-week) constrained to a minimum interval of once per day.
 *
 * Self-contained: no third-party cron library is pulled in. Each field accepts
 * the usual syntax — `*`, lists (`1,2`), ranges (`1-5`), steps (`*​/2`,
 * `0-30/5`). The minimum-daily rule (OpenAPI: "Minimum interval: daily") is
 * enforced by requiring the minute and hour fields to each resolve to a single
 * instant, forbidding intra-day repetition.
 */
final readonly class CronExpression
{
    private const FIELD_BOUNDS = [
        [0, 59],   // minute
        [0, 23],   // hour
        [1, 31],   // day of month
        [1, 12],   // month
        [0, 6],    // day of week (0 = Sunday)
    ];

    private const MAX_SEARCH_DAYS = 1_500;

    /**
     * @param array{0: list<int>, 1: list<int>, 2: list<int>, 3: list<int>, 4: list<int>} $fields
     */
    private function __construct(
        private string $value,
        private array $fields,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $splitResult = preg_split('/\s+/', $trimmed);
        $parts = $splitResult !== false ? $splitResult : [];

        if (count($parts) !== 5) {
            throw InvalidScheduleException::malformedCron($value);
        }

        $fields = [];
        foreach ($parts as $index => $part) {
            [$min, $max] = self::FIELD_BOUNDS[$index];
            $fields[$index] = self::parseField($value, $part, $min, $max);
        }

        // Minimum interval is daily: the schedule must fire at one specific
        // minute of one specific hour, never multiple times within a day.
        if (count($fields[0]) !== 1 || count($fields[1]) !== 1) {
            throw InvalidScheduleException::subDailyCron($value);
        }

        /** @var array{0: list<int>, 1: list<int>, 2: list<int>, 3: list<int>, 4: list<int>} $fields */
        return new self($trimmed, $fields);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * The first instant strictly after $after (interpreted in $timezone) that
     * matches the expression. Returned as a UTC instant for storage.
     *
     * Because the schedule is at-most daily, each calendar day has exactly one
     * candidate instant (the configured hour:minute); we walk forward day by day
     * until the day-of-month / month / day-of-week constraints all match.
     */
    public function nextRunAfter(DateTimeImmutable $after, DateTimeZone $timezone): DateTimeImmutable
    {
        $local = $after->setTimezone($timezone);
        $candidate = $local->setTime($this->fields[1][0], $this->fields[0][0], 0);

        if ($candidate <= $local) {
            $candidate = $candidate->modify('+1 day');
        }

        for ($day = 0; $day <= self::MAX_SEARCH_DAYS; ++$day) {
            if ($this->matches($candidate)) {
                return $candidate->setTimezone(new DateTimeZone('UTC'));
            }

            $candidate = $candidate->modify('+1 day')->setTime($this->fields[1][0], $this->fields[0][0], 0);
        }

        throw InvalidScheduleException::unsatisfiableCron($this->value);
    }

    private function matches(DateTimeImmutable $instant): bool
    {
        return in_array((int) $instant->format('i'), $this->fields[0], true)
            && in_array((int) $instant->format('H'), $this->fields[1], true)
            && in_array((int) $instant->format('j'), $this->fields[2], true)
            && in_array((int) $instant->format('n'), $this->fields[3], true)
            && in_array((int) $instant->format('w'), $this->fields[4], true);
    }

    /**
     * @return list<int>
     */
    private static function parseField(string $expression, string $field, int $min, int $max): array
    {
        $values = [];

        foreach (explode(',', $field) as $term) {
            foreach (self::parseTerm($expression, $term, $min, $max) as $value) {
                $values[$value] = true;
            }
        }

        $resolved = array_keys($values);
        sort($resolved);

        /** @var list<int> $resolved */
        return $resolved;
    }

    /**
     * @return list<int>
     */
    private static function parseTerm(string $expression, string $term, int $min, int $max): array
    {
        $step = 1;
        $range = $term;

        if (str_contains($term, '/')) {
            [$range, $stepRaw] = explode('/', $term, 2);
            if (!ctype_digit($stepRaw) || (int) $stepRaw < 1) {
                throw InvalidScheduleException::malformedCron($expression);
            }
            $step = (int) $stepRaw;
        }

        if ($range === '*') {
            $from = $min;
            $to = $max;
        } elseif (str_contains($range, '-')) {
            [$fromRaw, $toRaw] = explode('-', $range, 2);
            $from = self::parseNumber($expression, $fromRaw, $min, $max);
            $to = self::parseNumber($expression, $toRaw, $min, $max);
            if ($from > $to) {
                throw InvalidScheduleException::malformedCron($expression);
            }
        } else {
            $from = $to = self::parseNumber($expression, $range, $min, $max);
        }

        $values = [];
        for ($value = $from; $value <= $to; $value += $step) {
            $values[] = $value;
        }

        return $values;
    }

    private static function parseNumber(string $expression, string $raw, int $min, int $max): int
    {
        if (!ctype_digit($raw)) {
            throw InvalidScheduleException::malformedCron($expression);
        }

        $number = (int) $raw;
        if ($number < $min || $number > $max) {
            throw InvalidScheduleException::malformedCron($expression);
        }

        return $number;
    }
}
