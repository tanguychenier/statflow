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

use App\Reporting\Domain\Exception\InvalidEmailException;

/**
 * A recipient email address for scheduled reports, alert notifications and
 * export-ready notices.
 *
 * Validation is intentionally pragmatic: the address must satisfy PHP's
 * RFC-aligned filter and stay within a sane length bound. The local part keeps
 * its original case while the domain is lower-cased for stable comparison.
 */
final readonly class EmailAddress implements \Stringable
{
    public const MAX_LENGTH = 254;

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

        if ($trimmed === '') {
            throw InvalidEmailException::empty();
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidEmailException::malformed($trimmed);
        }

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailException::malformed($trimmed);
        }

        return new self(self::normalise($trimmed));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalise(string $address): string
    {
        $at = strrpos($address, '@');
        if ($at === false) {
            return $address;
        }

        return substr($address, 0, $at) . strtolower(substr($address, $at));
    }
}
