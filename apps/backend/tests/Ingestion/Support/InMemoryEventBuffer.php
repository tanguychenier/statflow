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

use App\Ingestion\Domain\Exception\BufferUnavailable;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Port\EventBufferPort;
use RuntimeException;

/**
 * Test double for EventBufferPort: records appended events and can be primed to
 * fail (to exercise the 503 path).
 */
final class InMemoryEventBuffer implements EventBufferPort
{
    /**
     * @var list<BufferedEvent>
     */
    public array $events = [];

    private bool $shouldFail = false;

    public function append(array $events): void
    {
        if ($this->shouldFail) {
            throw BufferUnavailable::fromCause(new RuntimeException('boom'));
        }

        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    public function failNext(): void
    {
        $this->shouldFail = true;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
