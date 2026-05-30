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

/**
 * A single page of results plus the navigation metadata required by the
 * `PaginatedResponse` envelope (`docs/api/README.md §3`).
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        public array $items,
        public ?Cursor $nextCursor,
        public ?Cursor $prevCursor,
        public int $limit,
    ) {
    }

    /**
     * @template U
     *
     * @param list<U> $items
     *
     * @return self<U>
     */
    public static function create(array $items, int $limit, ?Cursor $nextCursor = null, ?Cursor $prevCursor = null): self
    {
        return new self($items, $nextCursor, $prevCursor, $limit);
    }

    /**
     * @return self<mixed>
     */
    public static function empty(int $limit): self
    {
        return new self([], null, null, $limit);
    }

    public function hasNext(): bool
    {
        return $this->nextCursor !== null;
    }

    public function hasPrev(): bool
    {
        return $this->prevCursor !== null;
    }
}
