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

namespace App\Tests\Stub;

use App\Analytics\Domain\Port\ClickHouseClientPort;

/**
 * No-op ClickHouse client for the test environment.
 * Prevents real HTTP calls to ClickHouse during integration tests.
 */
final class NullClickHouseClient implements ClickHouseClientPort
{
    public function select(string $query, array $bindings = []): array
    {
        return [];
    }

    public function insert(string $table, array $rows): void
    {
        // intentionally a no-op in tests
    }
}
