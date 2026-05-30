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

use App\Sites\Domain\Exception\InvalidSiteNameException;

/**
 * Human-readable site label shown in the dashboard.
 *
 * The API constrains it to 1–200 characters; the PostgreSQL column allows up to
 * 255. We enforce the tighter API bound. Leading/trailing whitespace is trimmed
 * and never persisted, but interior whitespace is preserved verbatim.
 */
final readonly class SiteName implements \Stringable
{
    public const MAX_LENGTH = 200;

    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidSiteNameException::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidSiteNameException::tooLong(self::MAX_LENGTH);
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

    public function value(): string
    {
        return $this->value;
    }
}
