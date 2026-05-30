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

namespace App\Shared\Domain\Bus\Query;

/**
 * Marker interface for query handlers.
 *
 * Concrete handlers implement `__invoke(Query $query): QueryResult`, narrowing
 * both types. The wiring agent autoconfigures implementers onto the `query.bus`.
 */
interface QueryHandler
{
}
