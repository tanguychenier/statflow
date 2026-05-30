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

use App\Reporting\Application\Handler\ProcessExportHandler;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Messenger consumer that unwraps an {@see ExportRequested} job and delegates to
 * the {@see ProcessExportHandler} use-case, keeping the Application layer free of
 * transport concerns. Bound to the async transport by the wiring agent.
 */
#[AsMessageHandler]
final readonly class ProcessExportMessageHandler
{
    public function __construct(
        private ProcessExportHandler $handler,
    ) {
    }

    public function __invoke(ExportRequested $message): void
    {
        $this->handler->process(Uuid::fromString($message->exportId));
    }
}
