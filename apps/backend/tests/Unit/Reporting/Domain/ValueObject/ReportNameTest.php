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

use App\Reporting\Domain\Exception\InvalidReportNameException;
use App\Reporting\Domain\ValueObject\ReportDescription;
use App\Reporting\Domain\ValueObject\ReportName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportName::class)]
#[CoversClass(ReportDescription::class)]
#[CoversClass(InvalidReportNameException::class)]
final class ReportNameTest extends TestCase
{
    #[Test]
    public function itTrimsAndKeepsName(): void
    {
        self::assertSame('Weekly traffic', ReportName::fromString('  Weekly traffic  ')->value());
        self::assertSame('Weekly traffic', (string) ReportName::fromString('Weekly traffic'));
    }

    #[Test]
    public function itRejectsBlankName(): void
    {
        $this->expectException(InvalidReportNameException::class);
        ReportName::fromString('   ');
    }

    #[Test]
    public function itRejectsOverlongName(): void
    {
        $this->expectException(InvalidReportNameException::class);
        ReportName::fromString(str_repeat('a', ReportName::MAX_LENGTH + 1));
    }

    #[Test]
    public function descriptionNormalisesBlankToNull(): void
    {
        self::assertNull(ReportDescription::fromNullableString(null));
        self::assertNull(ReportDescription::fromNullableString('   '));
        self::assertSame('A note', ReportDescription::fromNullableString(' A note ')?->value());
    }

    #[Test]
    public function descriptionRejectsOverlong(): void
    {
        $this->expectException(InvalidReportNameException::class);
        ReportDescription::fromNullableString(str_repeat('x', ReportDescription::MAX_LENGTH + 1));
    }
}
