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

/**
 * Marker interface for domain-event listeners/projectors.
 *
 * Concrete handlers implement `__invoke({DomainEvent} $event): void`. The wiring
 * agent autoconfigures implementers onto the `event.bus`, which tolerates events
 * with no registered handler.
 */
interface EventHandler
{
}
