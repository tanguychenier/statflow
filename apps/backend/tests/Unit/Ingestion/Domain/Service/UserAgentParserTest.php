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

use App\Ingestion\Domain\Service\UserAgentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserAgentParser::class)]
final class UserAgentParserTest extends TestCase
{
    private UserAgentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new UserAgentParser();
    }

    #[Test]
    public function itParsesWindowsChrome(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        );

        self::assertSame('desktop', $device->deviceType);
        self::assertSame('Chrome', $device->browser);
        self::assertSame('120', $device->browserVersion);
        self::assertSame('Windows', $device->os);
        self::assertSame('10', $device->osVersion);
    }

    #[Test]
    public function itParsesIphoneSafariAsMobileIos(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        );

        self::assertSame('mobile', $device->deviceType);
        self::assertSame('Safari', $device->browser);
        self::assertSame('iOS', $device->os);
        self::assertSame('17', $device->osVersion);
    }

    #[Test]
    public function itParsesIpadAsTablet(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Version/16.0 Safari/604.1',
        );

        self::assertSame('tablet', $device->deviceType);
        self::assertSame('iOS', $device->os);
    }

    #[Test]
    public function itParsesAndroidPhoneAsMobile(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (Linux; Android 14; Pixel 8 Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        self::assertSame('mobile', $device->deviceType);
        self::assertSame('Android', $device->os);
        self::assertSame('14', $device->osVersion);
        self::assertSame('Chrome', $device->browser);
    }

    #[Test]
    public function itParsesAndroidTabletAsTablet(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 Chrome/118.0.0.0 Safari/537.36',
        );

        self::assertSame('tablet', $device->deviceType);
    }

    #[Test]
    public function itParsesFirefoxOnMac(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Gecko/20100101 Firefox/121.0',
        );

        self::assertSame('Firefox', $device->browser);
        self::assertSame('121', $device->browserVersion);
        self::assertSame('macOS', $device->os);
    }

    #[Test]
    public function itParsesEdgeBeforeChrome(): void
    {
        $device = $this->parser->parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        );

        self::assertSame('Edge', $device->browser);
    }

    #[Test]
    public function emptyUserAgentResolvesToUnknown(): void
    {
        $device = $this->parser->parse('');

        self::assertSame('desktop', $device->deviceType);
        self::assertSame('', $device->browser);
        self::assertSame('', $device->os);
    }

    #[Test]
    public function unknownLinuxResolvesToLinuxDesktop(): void
    {
        $device = $this->parser->parse('SomeUnknown/1.0 (X11; Linux x86_64)');

        self::assertSame('desktop', $device->deviceType);
        self::assertSame('Linux', $device->os);
        self::assertSame('', $device->browser);
    }
}
