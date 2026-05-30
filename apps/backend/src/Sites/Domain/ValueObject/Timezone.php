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

use App\Sites\Domain\Exception\InvalidTimezoneException;
use DateTimeZone;

/**
 * An IANA timezone identifier used for dashboard display (e.g. "Europe/Paris").
 *
 * Validation is against PHP's compiled IANA database rather than a regular
 * expression: only identifiers PHP recognises are accepted, so downstream date
 * formatting can never fail. "UTC" is the documented default.
 */
final readonly class Timezone implements \Stringable
{
    public const DEFAULT = 'UTC';

    private const MAX_LENGTH = 64;

    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidTimezoneException::empty();
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidTimezoneException::unknown($trimmed);
        }

        if (!in_array($trimmed, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidTimezoneException::unknown($trimmed);
        }

        $this->value = $trimmed;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function value(): string
    {
        return $this->value;
    }
}
