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

namespace App\Sites\Domain\Port;

use DateTimeImmutable;

/**
 * Driven port abstracting the current wall-clock time.
 *
 * Lets domain/application logic timestamp deletions and tracker-key grace
 * windows deterministically; tests substitute a frozen clock.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
