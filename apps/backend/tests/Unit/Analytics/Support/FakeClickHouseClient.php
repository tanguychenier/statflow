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

namespace App\Tests\Unit\Analytics\Support;

use App\Analytics\Domain\Port\ClickHouseClientPort;

/**
 * Records every SELECT and replays a programmed result per call, so handlers can
 * be tested against deterministic rows while asserting the generated SQL and
 * bindings.
 */
final class FakeClickHouseClient implements ClickHouseClientPort
{
    /**
     * @var list<array{sql: string, bindings: array<string, mixed>}>
     */
    public array $selectCalls = [];

    /**
     * @var list<array{table: string, rows: list<array<string, mixed>>}>
     */
    public array $insertCalls = [];

    private int $cursor = 0;

    /**
     * @param list<list<array<string, int|float|string|null>>> $results one result set per SELECT, in order
     */
    public function __construct(
        private array $results = []
    ) {
    }

    public function select(string $query, array $bindings = []): array
    {
        $this->selectCalls[] = [
            'sql' => $query,
            'bindings' => $bindings,
        ];

        return $this->results[$this->cursor++] ?? [];
    }

    public function insert(string $table, array $rows): void
    {
        $this->insertCalls[] = [
            'table' => $table,
            'rows' => $rows,
        ];
    }

    public function lastSql(): string
    {
        $last = end($this->selectCalls);

        return $last === false ? '' : $last['sql'];
    }
}
