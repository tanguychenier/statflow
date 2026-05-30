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

use App\Sites\Domain\Exception\InvalidSiteDomainException;

/**
 * A bare site hostname: no scheme, no port, no path, no trailing slash.
 *
 * Mirrors the `sites.domain` CHECK constraint in the PostgreSQL schema:
 * a dotted FQDN with a 2+ char TLD, or the literal `localhost`. The value is
 * normalised to lower case so domain uniqueness per team is case-insensitive.
 */
final readonly class Hostname implements \Stringable
{
    private const MAX_LENGTH = 253;

    private const FQDN_PATTERN = '/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/';

    private string $value;

    private function __construct(string $value)
    {
        $normalised = strtolower(trim($value));

        if ($normalised === '') {
            throw InvalidSiteDomainException::empty();
        }

        if (strlen($normalised) > self::MAX_LENGTH) {
            throw InvalidSiteDomainException::tooLong($normalised, self::MAX_LENGTH);
        }

        if ($normalised !== 'localhost' && preg_match(self::FQDN_PATTERN, $normalised) !== 1) {
            throw InvalidSiteDomainException::malformed($value);
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

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
