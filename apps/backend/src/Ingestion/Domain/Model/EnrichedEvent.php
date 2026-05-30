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
 * A canonical event after enrichment: the site UUID is resolved, identity/geo/
 * device/referrer-source fields are derived, and the row is ready to be written
 * to the ClickHouse `events` table (clickhouse-schema.sql §1).
 *
 * Built by the batch writer's enrichment step. Holding the canonical event plus
 * the derived bundles keeps the (large) ClickHouse column projection in one
 * place — the writer — instead of leaking it across the domain.
 */
final readonly class EnrichedEvent
{
    public function __construct(
        public string $siteId,
        public CanonicalEvent $event,
        public VisitorIdentity $identity,
        public GeoLocation $geo,
        public DeviceInfo $device,
        public string $referrerSource,
    ) {
    }
}
