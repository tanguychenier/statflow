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

namespace App\Ingestion\Domain\Exception;

use Throwable;

/**
 * The event buffer (Redis Streams) could not accept the event
 * (error-catalog.md `dependency-unavailable`, HTTP 503).
 */
final class BufferUnavailable extends IngestionException
{
    public static function fromCause(Throwable $cause): self
    {
        return new self('The ingestion buffer is temporarily unavailable.', 0, $cause);
    }

    public function slug(): string
    {
        return 'dependency-unavailable';
    }

    public function title(): string
    {
        return 'Dependency Unavailable';
    }

    public function status(): int
    {
        return 503;
    }
}
