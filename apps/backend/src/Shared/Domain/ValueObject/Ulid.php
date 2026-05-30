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

use InvalidArgumentException;

/**
 * Lexicographically sortable, time-ordered 128-bit identifier rendered as a
 * 26-character Crockford base32 string (uppercase, no padding).
 *
 * Statflow uses ULIDs exclusively for request correlation (`trace_id` in the
 * RFC 9457 Problem Details envelope). All persisted/domain identifiers are
 * UUIDs — see {@see Uuid} and `docs/api/README.md §10`.
 *
 * Implemented without an external dependency to keep the Domain layer free of
 * framework and library coupling (ADR-0004).
 *
 * @see https://github.com/ulid/spec
 */
final readonly class Ulid implements \Stringable
{
    /**
     * Crockford base32 alphabet — excludes I, L, O and U to avoid ambiguity.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LENGTH = 26;

    /**
     * Largest timestamp ULID can encode: 48 bits of milliseconds.
     */
    private const MAX_TIMESTAMP = 281474976710655;

    private string $value;

    private function __construct(string $value)
    {
        $normalised = strtoupper($value);

        if (!self::isValid($normalised)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid ULID.', $value));
        }

        $this->value = $normalised;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generate a new ULID for the given Unix-millisecond timestamp (defaults to
     * the current time). The 80 random bits guarantee uniqueness within a tick.
     */
    public static function generate(?int $unixTimeMs = null): self
    {
        $timestamp = $unixTimeMs ?? (int) (microtime(true) * 1000);

        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP) {
            throw new InvalidArgumentException('ULID timestamp is out of the representable range.');
        }

        return new self(self::encodeTime($timestamp) . self::encodeRandomness());
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public static function isValid(string $value): bool
    {
        if (strlen($value) !== self::LENGTH) {
            return false;
        }

        return strspn(strtoupper($value), self::ALPHABET) === self::LENGTH;
    }

    private static function encodeTime(int $timestamp): string
    {
        $encoded = '';

        for ($i = 0; $i < 10; ++$i) {
            $encoded = self::ALPHABET[$timestamp % 32] . $encoded;
            $timestamp = intdiv($timestamp, 32);
        }

        return $encoded;
    }

    private static function encodeRandomness(): string
    {
        $encoded = '';

        // 16 base32 symbols carry the 80 random bits of the ULID payload.
        for ($i = 0; $i < 16; ++$i) {
            $encoded .= self::ALPHABET[random_int(0, 31)];
        }

        return $encoded;
    }
}
