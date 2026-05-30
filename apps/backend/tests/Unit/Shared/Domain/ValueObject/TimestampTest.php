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

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Timestamp;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Timestamp::class)]
final class TimestampTest extends TestCase
{
    /**
     * Unix milliseconds for 2025-06-15T14:30:00.123Z.
     */
    private const SAMPLE_MS = 1_749_997_800_123;

    #[Test]
    public function itConvertsUnixMillisecondsToCanonicalIso8601(): void
    {
        $timestamp = Timestamp::fromUnixMilliseconds(self::SAMPLE_MS);

        self::assertSame('2025-06-15T14:30:00.123Z', $timestamp->toIso8601());
    }

    #[Test]
    public function itRoundTripsMilliseconds(): void
    {
        self::assertSame(self::SAMPLE_MS, Timestamp::fromUnixMilliseconds(self::SAMPLE_MS)->toUnixMilliseconds());
    }

    #[Test]
    public function itNormalisesNonUtcDateTimesToUtc(): void
    {
        $paris = new DateTimeImmutable('2025-06-15T16:30:00.000', new DateTimeZone('Europe/Paris'));

        self::assertSame('2025-06-15T14:30:00.000Z', Timestamp::fromDateTime($paris)->toIso8601());
    }

    #[Test]
    public function itParsesIso8601WithMilliseconds(): void
    {
        $timestamp = Timestamp::fromIso8601('2025-06-15T14:30:00.123Z');

        self::assertSame(self::SAMPLE_MS, $timestamp->toUnixMilliseconds());
    }

    #[Test]
    public function itParsesIso8601WithoutMilliseconds(): void
    {
        $timestamp = Timestamp::fromIso8601('2025-06-15T14:30:00Z');

        self::assertSame('2025-06-15T14:30:00.000Z', $timestamp->toIso8601());
    }

    #[Test]
    public function itParsesIso8601WithOffsetAndNormalisesToUtc(): void
    {
        $timestamp = Timestamp::fromIso8601('2025-06-15T16:30:00+02:00');

        self::assertSame('2025-06-15T14:30:00.000Z', $timestamp->toIso8601());
    }

    #[Test]
    public function itRejectsAnUnparseableIso8601String(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Timestamp::fromIso8601('15/06/2025');
    }

    #[Test]
    public function itRejectsNegativeMilliseconds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Timestamp::fromUnixMilliseconds(-1);
    }

    #[Test]
    public function itComparesInstants(): void
    {
        $earlier = Timestamp::fromUnixMilliseconds(1_000);
        $later = Timestamp::fromUnixMilliseconds(2_000);

        self::assertTrue($earlier->isBefore($later));
        self::assertTrue($later->isAfter($earlier));
        self::assertFalse($earlier->isAfter($later));
        self::assertFalse($earlier->equals($later));
        self::assertTrue($earlier->equals(Timestamp::fromUnixMilliseconds(1_000)));
    }

    #[Test]
    public function toStringMatchesIso8601(): void
    {
        $timestamp = Timestamp::fromUnixMilliseconds(self::SAMPLE_MS);

        self::assertSame($timestamp->toIso8601(), (string) $timestamp);
    }

    #[Test]
    public function itExposesAnImmutableUtcDateTime(): void
    {
        $dateTime = Timestamp::fromUnixMilliseconds(self::SAMPLE_MS)->toDateTimeImmutable();

        self::assertSame('UTC', $dateTime->getTimezone()->getName());
    }
}
