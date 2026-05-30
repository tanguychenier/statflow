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

use App\Ingestion\Domain\Model\EnrichedEvent;

/**
 * Driven port: batch-insert enriched events into the analytical store. The
 * production adapter targets the ClickHouse `events` table via a single bulk
 * INSERT (architecture.md §"Batch Writer Worker").
 */
interface EventWriterPort
{
    /**
     * @param list<EnrichedEvent> $events
     */
    public function writeBatch(array $events): void;
}
