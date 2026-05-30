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

namespace App\Tests\Unit\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\UnknownDimension;
use App\Analytics\Domain\ValueObject\Dimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dimension::class)]
#[CoversClass(UnknownDimension::class)]
final class DimensionTest extends TestCase
{
    #[Test]
    public function itMapsCountryToTheCountryCodeColumn(): void
    {
        self::assertSame('country_code', Dimension::Country->eventColumn());
        self::assertSame('country_code', Dimension::Country->sessionColumn());
    }

    #[Test]
    public function itDerivesReferrerDomainFromTheReferrerUrl(): void
    {
        self::assertSame('domainWithoutWWW(referrer)', Dimension::ReferrerDomain->eventColumn());
    }

    #[Test]
    public function itMapsAPlainDimensionToItsOwnColumn(): void
    {
        self::assertSame('pathname', Dimension::Pathname->eventColumn());
        self::assertSame('device_type', Dimension::DeviceType->eventColumn());
    }

    #[Test]
    public function entryAndExitPagesAreSessionScoped(): void
    {
        self::assertTrue(Dimension::EntryPage->isSessionScoped());
        self::assertTrue(Dimension::ExitPage->isSessionScoped());
        self::assertFalse(Dimension::Pathname->isSessionScoped());
    }

    #[Test]
    public function entryPageHasNoEventColumn(): void
    {
        $this->expectException(UnknownDimension::class);

        Dimension::EntryPage->eventColumn();
    }

    #[Test]
    public function itResolvesSessionScopedColumns(): void
    {
        self::assertSame('entry_page', Dimension::EntryPage->sessionColumn());
        self::assertSame('exit_page', Dimension::ExitPage->sessionColumn());
    }

    #[Test]
    public function itRejectsAnUnknownDimension(): void
    {
        $this->expectException(UnknownDimension::class);

        Dimension::fromString('moon_phase');
    }
}
