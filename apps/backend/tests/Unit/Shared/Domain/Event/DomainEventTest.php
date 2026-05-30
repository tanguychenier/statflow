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

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Timestamp;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainEvent::class)]
final class DomainEventTest extends TestCase
{
    #[Test]
    public function itGeneratesIdentityAndTimeWhenNoneSupplied(): void
    {
        $event = $this->makeEvent();

        self::assertInstanceOf(Uuid::class, $event->getEventId());
        self::assertInstanceOf(DateTimeImmutable::class, $event->getOccurredAt());
    }

    #[Test]
    public function itAcceptsExplicitIdentityAndTimeForReconstitution(): void
    {
        $id = Uuid::generate();
        $occurredAt = new DateTimeImmutable('2025-06-15T14:30:00.123Z');

        $event = $this->makeEvent('site-1', $id, $occurredAt);

        self::assertTrue($id->equals($event->getEventId()));
        self::assertEquals($occurredAt, $event->getOccurredAt());
    }

    #[Test]
    public function itExposesTheOccurredAtAsACanonicalTimestamp(): void
    {
        $event = $this->makeEvent('site-1', null, new DateTimeImmutable('2025-06-15T14:30:00.123Z'));

        self::assertInstanceOf(Timestamp::class, $event->occurredAtTimestamp());
        self::assertSame('2025-06-15T14:30:00.123Z', $event->occurredAtTimestamp()->toIso8601());
    }

    #[Test]
    public function itExposesNamePayloadAndAggregateId(): void
    {
        $event = $this->makeEvent('site-42');

        self::assertSame('test.thing_happened', $event->getEventName());
        self::assertSame('site-42', $event->getAggregateId());
        self::assertSame([
            'aggregate_id' => 'site-42',
        ], $event->payload());
    }

    private function makeEvent(
        string $aggregateId = 'site-1',
        ?Uuid $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ): DomainEvent {
        return new class($aggregateId, $eventId, $occurredAt) extends DomainEvent {
            public function __construct(
                private readonly string $aggregateId,
                ?Uuid $eventId,
                ?DateTimeImmutable $occurredAt,
            ) {
                parent::__construct($eventId, $occurredAt);
            }

            public function getEventName(): string
            {
                return 'test.thing_happened';
            }

            public function getAggregateId(): string
            {
                return $this->aggregateId;
            }

            public function payload(): array
            {
                return [
                    'aggregate_id' => $this->aggregateId,
                ];
            }
        };
    }
}
