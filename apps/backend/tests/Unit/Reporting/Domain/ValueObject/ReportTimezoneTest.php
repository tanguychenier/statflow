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

namespace App\Tests\Unit\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidScheduleException;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportTimezone::class)]
final class ReportTimezoneTest extends TestCase
{
    #[Test]
    public function itAcceptsKnownIdentifiers(): void
    {
        self::assertSame('Europe/Paris', ReportTimezone::fromString('Europe/Paris')->value());
        self::assertSame('UTC', ReportTimezone::default()->value());
        self::assertSame('Europe/Paris', (string) ReportTimezone::fromString('Europe/Paris'));
        self::assertSame('Europe/Paris', ReportTimezone::fromString('Europe/Paris')->toDateTimeZone()->getName());
    }

    #[Test]
    public function itRejectsUnknownTimezone(): void
    {
        $this->expectException(InvalidScheduleException::class);
        ReportTimezone::fromString('Mars/Phobos');
    }

    #[Test]
    public function itRejectsBlankTimezone(): void
    {
        $this->expectException(InvalidScheduleException::class);
        ReportTimezone::fromString('  ');
    }
}
