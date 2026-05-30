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
 * Driven port for dispatching {@see Query}s and returning their result.
 *
 * Cross-context reads go through this bus (architecture.md "Context interaction
 * rules") rather than reaching into another context's repositories. The
 * infrastructure adapter wraps the `query.bus` Messenger bus and unwraps the
 * single handler's returned {@see QueryResult}.
 */
interface QueryBus
{
    public function ask(Query $query): QueryResult;
}
