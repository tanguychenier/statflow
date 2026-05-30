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
 * A finished SQL statement paired with its parameter bindings, ready to hand to
 * the {@see \App\Analytics\Domain\Port\ClickHouseClientPort}.
 */
final readonly class SqlClause
{
    /**
     * @param array<string, scalar|list<scalar>> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
    ) {
    }
}
