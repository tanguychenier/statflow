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

use App\Identity\Domain\Exception\InvalidTeamSlugException;

/**
 * URL-safe team identifier. Mirrors the teams_slug_format CHECK constraint:
 * lowercase alphanumerics and single hyphens, 1–63 characters, no leading or
 * trailing hyphen.
 */
final readonly class TeamSlug implements \Stringable
{
    public const MAX_LENGTH = 63;

    private const PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    private string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidTeamSlugException::forValue($value);
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

    /**
     * Derive a candidate slug from a free-form team name. Uniqueness is the
     * repository's concern; this only produces a syntactically valid base.
     */
    public static function fromName(string $name): self
    {
        $ascii = self::toAscii($name);
        $slug = strtolower($ascii);
        // Drop apostrophes and similar intra-word punctuation before collapsing
        // separators, so "Alice's" → "alices" rather than "alice-s".
        $slug = (string) preg_replace("/['\u{2018}\u{2019}\u{201B}`]/u", '', $slug);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, self::MAX_LENGTH);
        $slug = rtrim($slug, '-');

        if ($slug === '') {
            $slug = 'team';
        }

        return new self($slug);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Append a numeric suffix for collision resolution, keeping the result
     * within the maximum length.
     */
    public function withSuffix(int $suffix): self
    {
        $suffixPart = '-' . $suffix;
        $base = substr($this->value, 0, self::MAX_LENGTH - strlen($suffixPart));
        $base = rtrim($base, '-');

        return new self($base . $suffixPart);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function toAscii(string $value): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $transliterated === false ? $value : $transliterated;
    }
}
