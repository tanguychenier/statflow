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

namespace App\Tests\Unit\Shared\Infrastructure\Trace;

use App\Shared\Domain\Trace\TraceIdProvider;
use App\Shared\Domain\ValueObject\Ulid;
use App\Shared\Infrastructure\Trace\UlidTraceIdProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UlidTraceIdProvider::class)]
final class UlidTraceIdProviderTest extends TestCase
{
    #[Test]
    public function itImplementsThePort(): void
    {
        self::assertInstanceOf(TraceIdProvider::class, new UlidTraceIdProvider());
    }

    #[Test]
    public function itMintsAValidUlid(): void
    {
        $traceId = (new UlidTraceIdProvider())->current();

        self::assertTrue(Ulid::isValid($traceId));
    }

    #[Test]
    public function itIsStableWithinTheSameRequest(): void
    {
        $provider = new UlidTraceIdProvider();

        self::assertSame($provider->current(), $provider->current());
    }

    #[Test]
    public function distinctProvidersMintDistinctIds(): void
    {
        self::assertNotSame(
            (new UlidTraceIdProvider())->current(),
            (new UlidTraceIdProvider())->current(),
        );
    }
}
