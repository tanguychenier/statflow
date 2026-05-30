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

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Exception\BufferUnavailable;
use App\Ingestion\Domain\Model\BufferedEvent;

/**
 * Driven port: the durable buffer between the synchronous ingestion request and
 * the asynchronous batch writer. The production adapter is a Redis Stream.
 */
interface EventBufferPort
{
    /**
     * Append validated events to the buffer. Implementations should be
     * fire-and-forget and as fast as possible — this runs on the hot path.
     *
     * @param list<BufferedEvent> $events
     *
     * @throws BufferUnavailable when the buffer cannot accept the events
     */
    public function append(array $events): void;
}
