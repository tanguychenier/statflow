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
 * Marker interface for command handlers.
 *
 * Concrete handlers implement an `__invoke(Command $command): void` method whose
 * parameter type narrows the command they handle. The wiring agent autoconfigures
 * implementers onto the `command.bus`.
 */
interface CommandHandler
{
}
