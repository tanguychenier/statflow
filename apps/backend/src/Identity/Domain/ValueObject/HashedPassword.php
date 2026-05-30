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

use App\Identity\Domain\Exception\InvalidPasswordHashException;

/**
 * An opaque, already-computed password hash. The domain treats this as a black
 * box: verification is delegated to a PasswordHasherPort because the hashing
 * algorithm is an infrastructure concern (ADR-0009 mandates bcrypt for v1).
 */
final readonly class HashedPassword implements \Stringable
{
    private string $value;

    private function __construct(string $value)
    {
        if ($value === '') {
            throw InvalidPasswordHashException::empty();
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromHash(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
