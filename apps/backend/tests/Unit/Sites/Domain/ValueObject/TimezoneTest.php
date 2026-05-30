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

namespace App\Tests\Unit\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidTimezoneException;
use App\Sites\Domain\ValueObject\Timezone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Timezone::class)]
#[CoversClass(InvalidTimezoneException::class)]
final class TimezoneTest extends TestCase
{
    #[Test]
    public function itAcceptsKnownIanaIdentifiers(): void
    {
        self::assertSame('Europe/Paris', Timezone::fromString('Europe/Paris')->value());
        self::assertSame('UTC', Timezone::fromString('UTC')->value());
        self::assertSame('UTC', (string) Timezone::default());
    }

    #[Test]
    public function itRejectsEmptyTimezone(): void
    {
        $this->expectException(InvalidTimezoneException::class);

        Timezone::fromString('  ');
    }

    #[Test]
    public function itRejectsUnknownTimezone(): void
    {
        $this->expectException(InvalidTimezoneException::class);

        Timezone::fromString('Mars/Olympus');
    }

    #[Test]
    public function itRejectsOverlongInput(): void
    {
        $this->expectException(InvalidTimezoneException::class);

        Timezone::fromString(str_repeat('a', 65));
    }
}
