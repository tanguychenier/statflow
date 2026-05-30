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

namespace App\Analytics\Application\Query;

/**
 * Deterministic cache-key derivation for analytics queries.
 *
 * Two queries that produce the same SQL + bindings share a fingerprint, so the
 * cache hit rate is high even when callers reorder filters. The kind prefix
 * keeps different endpoints (aggregate vs breakdown) from colliding.
 */
final class QueryFingerprint
{
    /**
     * @param array<int, SqlClause> $clauses
     */
    public static function fromClauses(string $kind, array $clauses): string
    {
        $material = [];
        foreach ($clauses as $clause) {
            $material[] = $clause->sql;
            $material[] = $clause->bindings;
        }

        return sprintf(
            '%s:%s',
            $kind,
            substr(hash('xxh128', json_encode($material, JSON_THROW_ON_ERROR)), 0, 24),
        );
    }
}
