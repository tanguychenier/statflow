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
 * Marker interface for a write-side message dispatched on the `command.bus`.
 *
 * Commands express intent to change state, are handled exactly once, and return
 * nothing meaningful to the dispatcher (ADR-0004). Implementations are immutable
 * DTOs carrying only the data the handler needs.
 */
interface Command
{
}
