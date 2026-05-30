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

namespace App\Analytics\Domain\Port;

/**
 * Port (driven side) for ClickHouse analytical queries.
 *
 * All ClickHouse access in the Analytics, Reporting, and Ingestion (batch writer)
 * contexts must go through this interface. Implementations live in the
 * Infrastructure layer and use ClickHouse's HTTP interface.
 *
 * Bindings are passed as ClickHouse server-side query parameters
 * (`{name:Type}`), never string-interpolated, so the query layer is immune to
 * SQL injection: only operands are bound, identifiers come from closed enums.
 */
interface ClickHouseClientPort
{
    /**
     * Execute a SELECT query and return all rows.
     *
     * ClickHouse's JSON format serialises every column as a scalar value:
     * strings, integers, floats, or null. Nested structures are never returned
     * by the queries in this codebase.
     *
     * @param array<string, scalar|list<scalar>> $bindings Named query parameters
     *                                                      (keyed by `{name:Type}` placeholder name)
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * Execute an INSERT statement.
     *
     * @param list<array<string, mixed>> $rows Rows to insert
     */
    public function insert(string $table, array $rows): void;
}
