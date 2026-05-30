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

namespace App\Tests\Unit\Ingestion\Infrastructure;

use App\Ingestion\Infrastructure\Geo\MaxMindGeoLocator;
use App\Ingestion\Infrastructure\Geo\NullGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaxMindGeoLocator::class)]
#[CoversClass(NullGeoLocator::class)]
final class MaxMindGeoLocatorTest extends TestCase
{
    #[Test]
    public function itReturnsUnknownForAnInvalidIp(): void
    {
        $locator = new MaxMindGeoLocator('/nonexistent.mmdb');

        $location = $locator->locate('not-an-ip');

        self::assertSame('', $location->countryCode);
        self::assertSame('', $location->region);
        self::assertSame('', $location->city);
    }

    #[Test]
    public function itDegradesToUnknownWhenNoDatabaseIsMounted(): void
    {
        $locator = new MaxMindGeoLocator('/var/lib/statflow/does-not-exist.mmdb');

        self::assertSame('', $locator->locate('203.0.113.5')->countryCode);
    }

    #[Test]
    public function itReturnsUnknownForAnEmptyIp(): void
    {
        $locator = new MaxMindGeoLocator('/nonexistent.mmdb');

        self::assertSame('', $locator->locate('')->countryCode);
    }

    #[Test]
    public function nullLocatorAlwaysReturnsUnknown(): void
    {
        $location = (new NullGeoLocator())->locate('203.0.113.5');

        self::assertSame('', $location->countryCode);
        self::assertSame('', $location->city);
    }
}
