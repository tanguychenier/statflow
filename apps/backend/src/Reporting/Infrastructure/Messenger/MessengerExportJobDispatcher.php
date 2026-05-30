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

namespace App\Reporting\Infrastructure\Messenger;

use App\Reporting\Domain\Port\ExportJobDispatcher;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter for {@see ExportJobDispatcher} that enqueues an {@see ExportRequested}
 * message on the async bus. The export worker consumes it and generates the file
 * off the request thread.
 */
final readonly class MessengerExportJobDispatcher implements ExportJobDispatcher
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function dispatch(Uuid $exportId): void
    {
        $this->eventBus->dispatch(new ExportRequested($exportId->getValue()));
    }
}
