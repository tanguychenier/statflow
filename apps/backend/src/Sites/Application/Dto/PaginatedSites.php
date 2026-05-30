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

namespace App\Sites\Application\Dto;

/**
 * A page of {@see SiteView}s plus pagination metadata, shaped to the OpenAPI
 * `PaginatedResponse`. Cursor-based, so only forward (`next`) navigation is
 * advertised; `prev` is always absent for this keyset listing.
 */
final readonly class PaginatedSites
{
    /**
     * @param list<SiteView> $sites
     */
    public function __construct(
        public array $sites,
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
            'data' => array_map(static fn (SiteView $s): array => $s->toArray(), $this->sites),
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
