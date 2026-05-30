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
 * Marker interface for a read-side message dispatched on the `query.bus`.
 *
 * Queries are side-effect free and return a DTO ({@see QueryResult}). They are
 * immutable DTOs describing what to read (ADR-0004).
 */
interface Query
{
}
