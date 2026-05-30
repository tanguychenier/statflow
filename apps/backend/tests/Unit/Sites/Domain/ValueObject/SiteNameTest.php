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

use App\Sites\Domain\Exception\InvalidSiteNameException;
use App\Sites\Domain\ValueObject\SiteName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteName::class)]
#[CoversClass(InvalidSiteNameException::class)]
final class SiteNameTest extends TestCase
{
    #[Test]
    public function itTrimsAndKeepsInteriorWhitespace(): void
    {
        self::assertSame('My Site', SiteName::fromString('  My Site  ')->value());
        self::assertSame('My Site', (string) SiteName::fromString('My Site'));
    }

    #[Test]
    public function itRejectsEmptyName(): void
    {
        $this->expectException(InvalidSiteNameException::class);

        SiteName::fromString('   ');
    }

    #[Test]
    public function itAcceptsExactlyMaxLength(): void
    {
        $name = str_repeat('x', SiteName::MAX_LENGTH);

        self::assertSame($name, SiteName::fromString($name)->value());
    }

    #[Test]
    public function itRejectsNameOverMaxLength(): void
    {
        $this->expectException(InvalidSiteNameException::class);

        SiteName::fromString(str_repeat('x', SiteName::MAX_LENGTH + 1));
    }

    #[Test]
    public function itCountsMultibyteCharacters(): void
    {
        // 200 multibyte characters must still be accepted (length is char-based).
        $name = str_repeat('é', SiteName::MAX_LENGTH);

        self::assertSame(SiteName::MAX_LENGTH, mb_strlen(SiteName::fromString($name)->value()));
    }
}
