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

namespace App\Shared\Domain\Bus\Command;

/**
 * Driven port for dispatching {@see Command}s.
 *
 * Application code depends on this interface rather than on Symfony Messenger,
 * keeping the framework at arm's length. The infrastructure adapter wraps the
 * `command.bus` Messenger bus.
 */
interface CommandBus
{
    public function dispatch(Command $command): void;
}
