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

namespace App\Shared\Domain\Event;

use App\Shared\Domain\ValueObject\Timestamp;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Base class for domain events published on the `event.bus`.
 *
 * Every event is self-identifying: it carries a unique {@see Uuid}, the moment it
 * occurred, and a stable name. Subclasses expose their payload via {@see payload()}
 * so that an event-store or message transport can serialise them uniformly.
 */
abstract class DomainEvent
{
    private readonly Uuid $eventId;

    private readonly DateTimeImmutable $occurredAt;

    public function __construct(
        ?Uuid $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->eventId = $eventId ?? Uuid::generate();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function getEventId(): Uuid
    {
        return $this->eventId;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * The instant the event occurred, normalised to the canonical UTC/ms shape.
     */
    public function occurredAtTimestamp(): Timestamp
    {
        return Timestamp::fromDateTime($this->occurredAt);
    }

    /**
     * Stable, dot-delimited event name used for routing and storage,
     * e.g. `sites.site_registered`.
     */
    abstract public function getEventName(): string;

    /**
     * The identifier of the aggregate this event pertains to.
     */
    abstract public function getAggregateId(): string;

    /**
     * Serialisable payload (no domain objects, only scalars/arrays).
     *
     * @return array<string, mixed>
     */
    abstract public function payload(): array;
}
