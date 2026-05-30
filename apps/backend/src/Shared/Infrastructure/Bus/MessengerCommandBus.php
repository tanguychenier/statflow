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

use App\Shared\Domain\Bus\Command\Command;
use App\Shared\Domain\Bus\Command\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter binding the {@see CommandBus} port to the `command.bus` Messenger bus.
 *
 * Unwraps Messenger's {@see HandlerFailedException} so callers see the original
 * domain/validation exception thrown inside the handler rather than the wrapper.
 */
final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious();
            throw ($cause instanceof \Throwable ? $cause : $exception);
        }
    }
}
