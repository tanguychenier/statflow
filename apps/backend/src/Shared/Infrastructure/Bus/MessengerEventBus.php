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

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\Event\EventBus;
use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter binding the {@see EventBus} port to the `event.bus` Messenger bus.
 *
 * The `event.bus` is configured with `allow_no_handlers`, so publishing an event
 * with no listeners is a no-op rather than a failure (see messenger.yaml).
 */
final readonly class MessengerEventBus implements EventBus
{
    public function __construct(
        private MessageBusInterface $eventBus
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
