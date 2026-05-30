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

/**
 * Driven port: short-lived dedup store keyed by (site, event_id).
 *
 * Replaying the same `event_id` within the 24-hour window returns 204 without
 * re-buffering (openapi.yaml /events "Idempotency"). The production adapter is
 * Redis with a 24-hour TTL on each key.
 */
interface IdempotencyStorePort
{
    /**
     * Atomically record first-seen for an event id.
     *
     * @return bool true if this is the first time the id is seen (proceed),
     *              false if it was already recorded (skip — replay)
     */
    public function registerIfFirstSeen(string $siteId, string $eventId): bool;
}
