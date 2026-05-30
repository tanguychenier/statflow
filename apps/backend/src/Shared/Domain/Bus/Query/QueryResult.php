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
 * Marker interface for the read-model DTO returned by a {@see Query} handler.
 *
 * Query results never expose domain entities directly; they are flat, serialisable
 * projections shaped for the API response.
 */
interface QueryResult
{
}
