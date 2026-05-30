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

namespace App\Shared\Domain\Aggregate;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Base class for aggregate roots.
 *
 * An aggregate records the domain events it raises during a use case; the
 * application layer pulls them after persistence and publishes them on the
 * `event.bus`. Recording-then-releasing (rather than dispatching inline) keeps
 * the aggregate free of infrastructure and makes event publication transactional
 * with the write.
 */
abstract class AggregateRoot
{
    /**
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    /**
     * Return and clear the pending events. Idempotent: a second call yields [].
     *
     * @return list<DomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function hasRecordedEvents(): bool
    {
        return $this->recordedEvents !== [];
    }

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
