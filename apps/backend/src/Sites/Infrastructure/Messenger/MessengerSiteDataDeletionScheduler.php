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

namespace App\Sites\Infrastructure\Messenger;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Port\SiteDataDeletionScheduler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Schedules asynchronous purge of a deleted site's analytical data by emitting
 * a {@see SiteDataDeletionRequested} integration message on the event bus.
 * Decouples the synchronous deletion request from the (slow, cross-store) purge.
 */
final readonly class MessengerSiteDataDeletionScheduler implements SiteDataDeletionScheduler
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function scheduleDeletion(Uuid $siteId): void
    {
        $this->eventBus->dispatch(new SiteDataDeletionRequested($siteId->getValue()));
    }
}
