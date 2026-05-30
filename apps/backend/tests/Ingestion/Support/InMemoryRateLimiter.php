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

namespace App\Tests\Ingestion\Support;

use App\Ingestion\Domain\Model\RateLimitDecision;
use App\Ingestion\Domain\Port\RateLimiterPort;

/**
 * Test double for RateLimiterPort: allows up to a fixed number of events, then
 * denies. Records the total cost consumed.
 */
final class InMemoryRateLimiter implements RateLimiterPort
{
    public int $consumed = 0;

    public function __construct(
        private readonly int $limit = PHP_INT_MAX,
        private readonly int $retryAfter = 30,
    ) {
    }

    public function consume(string $siteId, int $cost = 1): RateLimitDecision
    {
        $this->consumed += $cost;

        return $this->consumed > $this->limit
            ? RateLimitDecision::denied($this->retryAfter)
            : RateLimitDecision::allowed();
    }
}
