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
 * Deterministic {@see Clock} returning a frozen instant.
 *
 * Lives in the Domain layer (not the test tree) so any context can use it in
 * unit tests without coupling to a framework test double. It can be advanced
 * to simulate the passage of time.
 */
final class FixedClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}
