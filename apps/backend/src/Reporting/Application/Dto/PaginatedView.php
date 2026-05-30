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

namespace App\Reporting\Application\Dto;

/**
 * A page of already-serialised resource rows plus pagination metadata, shaped to
 * the OpenAPI `PaginatedResponse`. Cursor-based, so only forward (`next`)
 * navigation is advertised; `prev` is always absent for these keyset listings.
 */
final readonly class PaginatedView
{
    /**
     * @param list<array<string, mixed>> $data
     */
    public function __construct(
        public array $data,
        public ?string $nextCursor,
        public int $limit,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'pagination' => [
                'next_cursor' => $this->nextCursor,
                'prev_cursor' => null,
                'has_next' => $this->nextCursor !== null,
                'has_prev' => false,
                'limit' => $this->limit,
            ],
        ];
    }
}
