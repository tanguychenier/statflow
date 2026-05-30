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

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Bus\MessengerEventBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(MessengerEventBus::class)]
final class MessengerEventBusTest extends TestCase
{
    #[Test]
    public function itPublishesEveryEventOnTheUnderlyingBus(): void
    {
        $messenger = new RecordingEventMessageBus();

        $first = $this->event();
        $second = $this->event();

        (new MessengerEventBus($messenger))->publish($first, $second);

        self::assertSame([$first, $second], $messenger->dispatched);
    }

    #[Test]
    public function publishingNoEventsIsANoOp(): void
    {
        $messenger = new RecordingEventMessageBus();

        (new MessengerEventBus($messenger))->publish();

        self::assertSame([], $messenger->dispatched);
    }

    private function event(): DomainEvent
    {
        return new class() extends DomainEvent {
            public function getEventName(): string
            {
                return 'test.published';
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
    }
}

/**
 * Test fixture: a message bus that records every dispatched event.
 */
final class RecordingEventMessageBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message);
    }
}
