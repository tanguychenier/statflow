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

use App\Identity\Domain\Exception\WeakPasswordException;

/**
 * A plaintext password supplied by a user, validated against the policy before
 * it is ever hashed. The single minimum-length rule (12 chars, ADR-0009 §2) is
 * enforced here so every entry point — register, login, change, reset — shares
 * one source of truth. The raw value never leaves the domain except to a hasher.
 */
final class PlainPassword
{
    public const MIN_LENGTH = 12;

    public const MAX_LENGTH = 128;

    private string $value;

    private function __construct(string $value)
    {
        $length = mb_strlen($value);

        if ($length < self::MIN_LENGTH) {
            throw WeakPasswordException::tooShort(self::MIN_LENGTH);
        }

        if ($length > self::MAX_LENGTH) {
            throw WeakPasswordException::tooLong(self::MAX_LENGTH);
        }

        $this->value = $value;
    }

    /**
     * Prevents the plaintext from leaking into logs, var_dump, or stack traces.
     */
    public function __debugInfo(): array
    {
        return [
            'value' => '********',
        ];
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Wrap a candidate password supplied solely to verify against an existing
     * hash (e.g. the current_password field on a change request). Policy checks
     * are skipped because the value is being matched, not stored, and the spec
     * imposes no minimum length on that field.
     */
    public static function forVerification(string $value): self
    {
        $instance = new self(str_repeat('x', self::MIN_LENGTH));
        $instance->value = $value;

        return $instance;
    }

    public function reveal(): string
    {
        return $this->value;
    }
}
