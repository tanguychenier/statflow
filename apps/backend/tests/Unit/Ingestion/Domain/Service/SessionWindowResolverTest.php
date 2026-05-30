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

namespace App\Tests\Unit\Ingestion\Domain\Service;

use App\Ingestion\Domain\Service\SessionWindowResolver;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionWindowResolver::class)]
final class SessionWindowResolverTest extends TestCase
{
    private SessionWindowResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SessionWindowResolver();
    }

    #[Test]
    public function theWindowIsTheFloorOfUnixSecondsDividedBySixty(): void
    {
        // ADR-0008 §2: session_window = floor(unix_seconds / 60).
        $timestamp = $this->at('2025-06-15T14:30:45Z');

        self::assertSame(
            intdiv($timestamp->getTimestamp(), 60),
            $this->resolver->resolve($timestamp),
        );
    }

    #[Test]
    public function eventsInTheSameMinuteShareAWindow(): void
    {
        $early = $this->resolver->resolve($this->at('2025-06-15T14:30:00Z'));
        $late = $this->resolver->resolve($this->at('2025-06-15T14:30:59Z'));

        self::assertSame($early, $late);
    }

    #[Test]
    public function eventsInAdjacentMinutesGetDifferentWindows(): void
    {
        $first = $this->resolver->resolve($this->at('2025-06-15T14:30:59Z'));
        $second = $this->resolver->resolve($this->at('2025-06-15T14:31:00Z'));

        self::assertSame(1, $second - $first);
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }
}
