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

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidEmailException;

/**
 * Case-insensitive email address. Stored and compared in lowercase to mirror
 * the PostgreSQL CITEXT column and the unique index on users.email.
 */
final readonly class EmailAddress implements \Stringable
{
    public const MAX_LENGTH = 254;

    private string $value;

    private function __construct(string $value)
    {
        $normalised = strtolower(trim($value));

        if ($normalised === '') {
            throw InvalidEmailException::empty();
        }

        if (mb_strlen($normalised) > self::MAX_LENGTH) {
            throw InvalidEmailException::tooLong(self::MAX_LENGTH);
        }

        // Mirrors the users_email_format CHECK constraint in postgres-schema.sql.
        if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $normalised) !== 1) {
            throw InvalidEmailException::malformed($value);
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

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        $atPos = strrpos($this->value, '@');
        return substr($this->value, $atPos === false ? 0 : $atPos + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
