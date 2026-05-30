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

use App\Shared\Domain\Exception\ValidationException;

/**
 * Validated inbound pagination parameters (`docs/api/README.md §3`).
 *
 * Enforces the documented bounds: `limit` defaults to 20, max 100; `direction`
 * is `next` or `prev`. Out-of-contract input raises a {@see ValidationException}
 * so the HTTP layer can return a 422 with field errors.
 */
final readonly class PaginationRequest
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    public const DIRECTION_NEXT = 'next';

    public const DIRECTION_PREV = 'prev';

    private function __construct(
        public int $limit,
        public string $direction,
        public ?Cursor $cursor,
    ) {
    }

    public static function fromPrimitives(
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT,
        string $direction = self::DIRECTION_NEXT,
    ): self {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw ValidationException::forField(
                'limit',
                'out_of_range',
                sprintf('limit must be between 1 and %d.', self::MAX_LIMIT),
            );
        }

        if ($direction !== self::DIRECTION_NEXT && $direction !== self::DIRECTION_PREV) {
            throw ValidationException::forField(
                'direction',
                'invalid_enum_value',
                'direction must be "next" or "prev".',
            );
        }

        $decodedCursor = null;

        if ($cursor !== null && $cursor !== '') {
            try {
                $decodedCursor = Cursor::decode($cursor);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::forField('cursor', 'invalid_format', 'cursor is malformed.', $e);
            }
        }

        return new self($limit, $direction, $decodedCursor);
    }

    public function isForward(): bool
    {
        return $this->direction === self::DIRECTION_NEXT;
    }
}
