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

namespace App\Tests\Unit\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\Clock;
use App\Shared\Infrastructure\Clock\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface as PsrClock;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function itIsADomainClock(): void
    {
        self::assertInstanceOf(Clock::class, new SystemClock($this->psrClock(new DateTimeImmutable())));
    }

    #[Test]
    public function itDelegatesToThePsrClock(): void
    {
        $frozen = new DateTimeImmutable('2025-06-15T14:30:00Z');
        $clock = new SystemClock($this->psrClock($frozen));

        self::assertEquals($frozen, $clock->now());
    }

    private function psrClock(DateTimeImmutable $now): PsrClock
    {
        return new class($now) implements PsrClock {
            public function __construct(
                private readonly DateTimeImmutable $now
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }
}
