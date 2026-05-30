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
 * A parameterised SQL fragment: the SQL text plus the named bindings it refers
 * to. Bindings use ClickHouse server-side parameter syntax (`{name:Type}`), so
 * the fragment carries no inlined user data.
 */
final readonly class BoundExpression
{
    /**
     * @param array<string, scalar|list<scalar>> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings = [],
    ) {
    }

    public static function alwaysTrue(): self
    {
        return new self('1 = 1');
    }
}
