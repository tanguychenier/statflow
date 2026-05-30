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

namespace App\Shared\Domain\Clock;

use DateTimeImmutable;

/**
 * Driven port providing the current time.
 *
 * Domain and application code must read "now" through this port instead of
 * instantiating {@see DateTimeImmutable} directly, so that time-dependent
 * behaviour is deterministic under test (ADR-0004). The production adapter
 * bridges PSR-20; tests use {@see \App\Shared\Domain\Clock\FixedClock}.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
