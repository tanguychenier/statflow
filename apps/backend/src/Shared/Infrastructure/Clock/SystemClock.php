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

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\Clock;
use DateTimeImmutable;
use Psr\Clock\ClockInterface as PsrClock;

/**
 * Production {@see Clock} adapter delegating to a PSR-20 clock.
 *
 * Bridging PSR-20 means the rest of the codebase depends only on the framework-
 * agnostic Domain port while still benefiting from Symfony's clock mocking.
 */
final readonly class SystemClock implements Clock
{
    public function __construct(
        private PsrClock $clock
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
