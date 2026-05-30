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

use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\BotDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BotDetector::class)]
final class BotDetectorTest extends TestCase
{
    private BotDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new BotDetector();
    }

    #[Test]
    #[DataProvider('botUserAgents')]
    public function itFlagsKnownAutomatedAgents(string $userAgent): void
    {
        self::assertTrue($this->detector->isBot($this->context($userAgent)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function botUserAgents(): iterable
    {
        yield 'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
        yield 'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'];
        yield 'facebook scraper' => ['facebookexternalhit/1.1'];
        yield 'curl' => ['curl/8.4.0'];
        yield 'python requests' => ['python-requests/2.31.0'];
        yield 'headless chrome' => ['Mozilla/5.0 HeadlessChrome/120.0.0.0'];
        yield 'gptbot' => ['Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)'];
        yield 'uptime monitor' => ['Mozilla/5.0 (compatible; UptimeRobot/2.0)'];
    }

    #[Test]
    public function itFlagsAnEmptyUserAgent(): void
    {
        self::assertTrue($this->detector->isBot($this->context('')));
    }

    #[Test]
    public function itFlagsAnImplausiblyShortUserAgent(): void
    {
        self::assertTrue($this->detector->isBot($this->context('x')));
    }

    #[Test]
    public function itPassesRealBrowsers(): void
    {
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

        self::assertFalse($this->detector->isBot($this->context($chrome)));
    }

    private function context(string $userAgent): RequestContext
    {
        return new RequestContext('203.0.113.5', $userAgent, 'en-US', null);
    }
}
