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

use App\Ingestion\Domain\Model\RateLimitDecision;

/**
 * Driven port: per-site ingestion rate limiting (ADR-0009 §1).
 *
 * The cost argument lets a batch consume N tokens in one call, so a 100-event
 * batch is charged as 100 events rather than one request.
 */
interface RateLimiterPort
{
    public function consume(string $siteId, int $cost = 1): RateLimitDecision;
}
