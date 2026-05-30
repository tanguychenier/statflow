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

use App\Reporting\Application\Handler\SendScheduledReportHandler;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Messenger consumer that delivers one due scheduled report via the
 * {@see SendScheduledReportHandler} use-case. Bound to the async transport.
 */
#[AsMessageHandler]
final readonly class SendScheduledReportMessageHandler
{
    public function __construct(
        private SendScheduledReportHandler $handler,
    ) {
    }

    public function __invoke(ScheduledReportDue $message): void
    {
        $this->handler->send(
            Uuid::fromString($message->siteId),
            Uuid::fromString($message->scheduledReportId),
        );
    }
}
