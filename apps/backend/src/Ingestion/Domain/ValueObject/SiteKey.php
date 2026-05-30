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

namespace App\Ingestion\Domain\ValueObject;

use App\Ingestion\Domain\Exception\InvalidTrackerKey;

/**
 * Public per-site ingestion key (ADR-0009). Prefix `stk_`, public by design.
 *
 * Validating the shape here keeps malformed keys from ever reaching the site
 * repository: a value that cannot be a key is rejected before any lookup.
 */
final readonly class SiteKey
{
    public const PREFIX = 'stk_';

    // Keys are minted as URL-safe base64 (RandomTrackerKeyGenerator), so the
    // alphabet includes '-' and '_' in addition to alphanumerics.
    private const PATTERN = '/^stk_[A-Za-z0-9_-]{16,64}$/';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidTrackerKey::malformed();
        }

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
