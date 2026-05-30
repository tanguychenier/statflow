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

namespace App\Ingestion\Domain\Model;

/**
 * What the ingestion handler hands to the buffer: the resolved site id, the
 * validated canonical event, and the request-inherent identity inputs needed by
 * the batch writer to compute visitor/session ids and geo/device fields.
 *
 * The buffer is the boundary between the synchronous request and the async
 * writer. The identity inputs must travel with the event because they are not
 * recoverable later (the request is long gone by the time the writer runs).
 */
final readonly class BufferedEvent
{
    public function __construct(
        public string $siteId,
        public CanonicalEvent $event,
        public RequestContext $context,
    ) {
    }
}
