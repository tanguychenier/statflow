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

use App\Ingestion\Domain\Port\IdempotencyStorePort;

/**
 * Test double for IdempotencyStorePort: remembers seen (site, event) pairs.
 */
final class InMemoryIdempotencyStore implements IdempotencyStorePort
{
    /**
     * @var array<string, true>
     */
    private array $seen = [];

    public function registerIfFirstSeen(string $siteId, string $eventId): bool
    {
        $key = $siteId . ':' . $eventId;
        if (isset($this->seen[$key])) {
            return false;
        }

        $this->seen[$key] = true;

        return true;
    }
}
