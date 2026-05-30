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

namespace App\Shared\Domain\Trace;

/**
 * Supplies the request-scoped correlation id (`trace_id`) rendered into every
 * RFC 9457 Problem Details body and written to structured logs.
 *
 * The id is stable for the lifetime of a single request so the value the client
 * receives matches the value in the server logs (`docs/api/error-catalog.md`).
 */
interface TraceIdProvider
{
    public function current(): string;
}
