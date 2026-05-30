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

namespace App\Tests\Unit\Shared\Infrastructure\Http\Fixture;

use App\Shared\Domain\Trace\TraceIdProvider;

/**
 * Test fixture: a deterministic trace-id provider.
 */
final class FixedTraceIdProvider implements TraceIdProvider
{
    public const TRACE_ID = '01HXK2Q4B7T8NMPZW6RDYGJ5FM';

    public function current(): string
    {
        return self::TRACE_ID;
    }
}
