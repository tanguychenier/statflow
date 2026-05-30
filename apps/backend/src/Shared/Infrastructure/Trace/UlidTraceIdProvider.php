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

namespace App\Shared\Infrastructure\Trace;

use App\Shared\Domain\Trace\TraceIdProvider;
use App\Shared\Domain\ValueObject\Ulid;

/**
 * Request-scoped {@see TraceIdProvider} that lazily mints a single ULID and
 * caches it for the rest of the request.
 *
 * Registered as a non-shared (request-scoped) service so each request gets its
 * own correlation id; within a request the id never changes.
 */
final class UlidTraceIdProvider implements TraceIdProvider
{
    private ?string $traceId = null;

    public function current(): string
    {
        return $this->traceId ??= Ulid::generate()->getValue();
    }
}
