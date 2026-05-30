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

namespace App\Tests\Unit\Shared\Domain\Aggregate;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRoot::class)]
final class AggregateRootTest extends TestCase
{
    #[Test]
    public function aFreshAggregateHasNoRecordedEvents(): void
    {
        $aggregate = new RecordingAggregate();

        self::assertFalse($aggregate->hasRecordedEvents());
        self::assertSame([], $aggregate->pullDomainEvents());
    }

    #[Test]
    public function itRecordsEventsInOrder(): void
    {
        $aggregate = new RecordingAggregate();

        $first = $aggregate->doSomething();
        $second = $aggregate->doSomething();

        self::assertTrue($aggregate->hasRecordedEvents());
        self::assertSame([$first, $second], $aggregate->pullDomainEvents());
    }

    #[Test]
    public function pullingEventsClearsThemAndIsIdempotent(): void
    {
        $aggregate = new RecordingAggregate();
        $aggregate->doSomething();

        $aggregate->pullDomainEvents();

        self::assertSame([], $aggregate->pullDomainEvents());
        self::assertFalse($aggregate->hasRecordedEvents());
    }
}

/**
 * Test fixture: an aggregate that records a trivial event on demand.
 */
final class RecordingAggregate extends AggregateRoot
{
    public function doSomething(): DomainEvent
    {
        $event = new class() extends DomainEvent {
            public function getEventName(): string
            {
                return 'test.something_done';
            }

            public function getAggregateId(): string
            {
                return 'agg-1';
            }

            public function payload(): array
            {
                return [];
            }
        };

        $this->record($event);

        return $event;
    }
}
