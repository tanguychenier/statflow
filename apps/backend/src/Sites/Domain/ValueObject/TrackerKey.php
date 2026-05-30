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

use App\Sites\Domain\Exception\InvalidTrackerKeyException;

/**
 * The public per-site ingestion identifier (ADR-0009).
 *
 * Prefixed `stk_`, carried in the event body, public by design. It identifies a
 * site for ingestion and grants nothing else. The random suffix comes from a
 * {@see \App\Sites\Domain\Port\TrackerKeyGenerator}; this value object only
 * guarantees the shape, so a malformed key can never reach the database.
 */
final readonly class TrackerKey implements \Stringable
{
    public const PREFIX = 'stk_';

    private const SUFFIX_PATTERN = '/^[A-Za-z0-9_-]{32,64}$/';

    private string $value;

    private function __construct(string $value)
    {
        if (!str_starts_with($value, self::PREFIX)) {
            throw InvalidTrackerKeyException::badPrefix($value);
        }

        $suffix = substr($value, strlen(self::PREFIX));

        if (preg_match(self::SUFFIX_PATTERN, $suffix) !== 1) {
            throw InvalidTrackerKeyException::badSuffix($value);
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
