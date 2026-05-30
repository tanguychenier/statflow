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

namespace App\Ingestion\Application\Command;

use App\Ingestion\Domain\Model\RequestContext;

/**
 * Command: ingest one or more raw events from a single HTTP request.
 *
 * The events are the raw decoded objects (wire or canonical form) exactly as
 * received; normalisation and validation happen in the handler so the command
 * stays a dumb carrier. `isBatch` distinguishes the single (/events) endpoint
 * from the batch (/events/batch) endpoint, which differ only in their response
 * shape, not their processing.
 */
final readonly class IngestEventsCommand
{
    /**
     * @param list<array<string, mixed>> $rawEvents
     */
    public function __construct(
        public array $rawEvents,
        public RequestContext $context,
        public bool $isBatch,
    ) {
    }
}
