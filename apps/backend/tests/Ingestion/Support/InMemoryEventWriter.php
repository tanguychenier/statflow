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

use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Port\EventWriterPort;

/**
 * Test double for EventWriterPort: records every batch written.
 */
final class InMemoryEventWriter implements EventWriterPort
{
    /**
     * @var list<list<EnrichedEvent>>
     */
    public array $batches = [];

    public function writeBatch(array $events): void
    {
        $this->batches[] = $events;
    }

    /**
     * @return list<EnrichedEvent>
     */
    public function allEvents(): array
    {
        return array_merge(...($this->batches !== [] ? $this->batches : [[]]));
    }
}
