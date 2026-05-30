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

namespace App\Tests\Unit\Shared\Domain\Clock;

use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixedClock::class)]
final class FixedClockTest extends TestCase
{
    #[Test]
    public function itIsAClock(): void
    {
        self::assertInstanceOf(Clock::class, new FixedClock(new DateTimeImmutable()));
    }

    #[Test]
    public function itReturnsTheFrozenInstant(): void
    {
        $now = new DateTimeImmutable('2025-06-15T14:30:00Z');
        $clock = new FixedClock($now);

        self::assertEquals($now, $clock->now());
        self::assertEquals($now, $clock->now());
    }

    #[Test]
    public function itCanBeReset(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2025-01-01T00:00:00Z'));
        $next = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $clock->set($next);

        self::assertEquals($next, $clock->now());
    }

    #[Test]
    public function itCanAdvance(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2025-06-15T14:30:00Z'));

        $clock->advance('+10 seconds');

        self::assertSame('2025-06-15T14:30:10', $clock->now()->format('Y-m-d\TH:i:s'));
    }
}
