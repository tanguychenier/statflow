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

namespace App\Shared\Domain\Bus\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Driven port for publishing {@see DomainEvent}s on the `event.bus`.
 *
 * Domain events are the only sanctioned channel for cross-context side effects
 * (architecture.md). The bus allows zero or many handlers per event; publishing
 * an event with no listeners is not an error. The infrastructure adapter wraps
 * the `event.bus` Messenger bus.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
