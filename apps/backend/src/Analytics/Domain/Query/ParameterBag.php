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

namespace App\Analytics\Domain\Query;

/**
 * Accumulates ClickHouse server-side query parameters and mints collision-free
 * placeholder names. The query builders hand operands here and embed only the
 * returned `{name:Type}` placeholder in the SQL, never the value itself.
 */
final class ParameterBag
{
    /**
     * @var array<string, scalar|list<scalar>>
     */
    private array $bindings = [];

    private int $sequence = 0;

    /**
     * Bind a value under a fresh name and return its `{name:Type}` placeholder.
     *
     * @param scalar|list<scalar> $value
     */
    public function bind(string $clickHouseType, string|int|float|bool|array $value, string $prefix = 'p'): string
    {
        $name = sprintf('%s%d', $prefix, $this->sequence++);
        $this->bindings[$name] = $value;

        return sprintf('{%s:%s}', $name, $clickHouseType);
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    public function all(): array
    {
        return $this->bindings;
    }
}
