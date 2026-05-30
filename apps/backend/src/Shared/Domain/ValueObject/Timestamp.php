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

namespace App\Shared\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * UTC instant with millisecond precision.
 *
 * The canonical Statflow timestamp representation is an ISO-8601 UTC string with
 * milliseconds (`2025-06-15T14:30:00.123Z`) — see the event contract §2.1 and
 * `docs/api/README.md §10`. This value object is the single place that conversion
 * is implemented, so every context emits an identical wire/storage shape.
 */
final readonly class Timestamp implements \Stringable
{
    /**
     * ISO-8601, milliseconds, literal `Z` zone designator.
     */
    public const ISO_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    private DateTimeImmutable $value;

    private function __construct(DateTimeImmutable $value)
    {
        $this->value = $value->setTimezone(new DateTimeZone('UTC'));
    }

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self(DateTimeImmutable::createFromInterface($dateTime));
    }

    /**
     * Build from a Unix-millisecond integer (the tracker wire format).
     */
    public static function fromUnixMilliseconds(int $milliseconds): self
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException('Timestamp milliseconds must not be negative.');
        }

        $seconds = intdiv($milliseconds, 1000);
        $remainder = $milliseconds % 1000;

        $dateTime = DateTimeImmutable::createFromFormat(
            'U.v',
            sprintf('%d.%03d', $seconds, $remainder),
            new DateTimeZone('UTC'),
        );

        if ($dateTime === false) {
            throw new InvalidArgumentException('Unable to build a timestamp from the supplied milliseconds.');
        }

        return new self($dateTime);
    }

    /**
     * Parse a canonical ISO-8601 string. Accepts any offset and normalises to UTC.
     */
    public static function fromIso8601(string $value): self
    {
        $dateTime = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $value);

        if ($dateTime === false) {
            $dateTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        }

        if ($dateTime === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid ISO-8601 timestamp.', $value));
        }

        return new self($dateTime);
    }

    public function toIso8601(): string
    {
        return $this->value->format(self::ISO_FORMAT);
    }

    public function toUnixMilliseconds(): int
    {
        return (int) $this->value->format('Uv');
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return $this->value;
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->toUnixMilliseconds() === $other->toUnixMilliseconds();
    }
}
