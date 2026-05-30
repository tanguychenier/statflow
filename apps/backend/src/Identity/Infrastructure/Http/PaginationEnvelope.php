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

namespace App\Identity\Infrastructure\Http;

/**
 * Wraps a fully materialised collection in the OpenAPI PaginatedResponse shape.
 *
 * Identity collections (teams, members, API keys) are small and bounded per user
 * or team, so v1 returns the whole set in a single page. The cursor fields are
 * present (contract conformance) but always null/false; cursor pagination can be
 * added later without changing the response shape.
 */
final class PaginationEnvelope
{
    /**
     * @param list<array<string, mixed>> $data
     *
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public static function singlePage(array $data, int $limit): array
    {
        return [
            'data' => $data,
            'pagination' => [
                'next_cursor' => null,
                'prev_cursor' => null,
                'has_next' => false,
                'has_prev' => false,
                'limit' => $limit,
            ],
        ];
    }
}
