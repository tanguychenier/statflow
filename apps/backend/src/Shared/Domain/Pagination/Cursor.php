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

namespace App\Shared\Domain\Pagination;

use InvalidArgumentException;

/**
 * Opaque base64url cursor for cursor-based pagination (`docs/api/README.md §3`).
 *
 * Clients treat the encoded form as opaque; servers encode the sort key(s) and
 * direction. Encoding is base64url *without* padding so the value is URL-safe.
 */
final readonly class Cursor
{
    /**
     * @param array<string, scalar> $position the decoded sort-key position
     */
    private function __construct(
        private array $position
    ) {
    }

    /**
     * @param array<string, scalar> $position
     */
    public static function fromPosition(array $position): self
    {
        return new self($position);
    }

    public static function decode(string $encoded): self
    {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            throw new InvalidArgumentException('Cursor is not valid base64url.');
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Cursor does not decode to an object.');
        }

        /** @var array<string, scalar> $decoded */
        return new self($decoded);
    }

    public function encode(): string
    {
        $json = json_encode($this->position, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, scalar>
     */
    public function position(): array
    {
        return $this->position;
    }
}
