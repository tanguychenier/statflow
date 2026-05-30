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

use App\Ingestion\Domain\Service\ReferrerClassifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferrerClassifier::class)]
final class ReferrerClassifierTest extends TestCase
{
    private ReferrerClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ReferrerClassifier();
    }

    #[Test]
    #[DataProvider('knownSources')]
    public function itClassifiesKnownSources(string $referrer, string $expected): void
    {
        self::assertSame($expected, $this->classifier->classify($referrer, 'example.com'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownSources(): iterable
    {
        yield 'google' => ['https://www.google.com/search?q=x', 'google'];
        yield 'bing' => ['https://www.bing.com/', 'bing'];
        yield 'twitter t.co' => ['https://t.co/abc', 'twitter'];
        yield 'x.com' => ['https://x.com/user', 'twitter'];
        yield 'facebook' => ['https://facebook.com/page', 'facebook'];
        yield 'linkedin short' => ['https://lnkd.in/xyz', 'linkedin'];
        yield 'youtube short' => ['https://youtu.be/abc', 'youtube'];
        yield 'hackernews' => ['https://news.ycombinator.com/item?id=1', 'hackernews'];
    }

    #[Test]
    public function emptyOrNullReferrerIsDirect(): void
    {
        self::assertSame('direct', $this->classifier->classify(null, 'example.com'));
        self::assertSame('direct', $this->classifier->classify('', 'example.com'));
        self::assertSame('direct', $this->classifier->classify('   ', 'example.com'));
    }

    #[Test]
    public function sameHostReferrerIsDirectInternalNavigation(): void
    {
        self::assertSame('direct', $this->classifier->classify('https://example.com/about', 'example.com'));
        self::assertSame('direct', $this->classifier->classify('https://www.example.com/about', 'example.com'));
    }

    #[Test]
    public function unknownExternalReferrerKeepsItsBareHost(): void
    {
        self::assertSame('blog.acme.io', $this->classifier->classify('https://blog.acme.io/post', 'example.com'));
    }

    #[Test]
    public function unknownExternalReferrerStripsWww(): void
    {
        self::assertSame('acme.io', $this->classifier->classify('https://www.acme.io/post', 'example.com'));
    }

    #[Test]
    public function aReferrerWithoutAHostIsDirect(): void
    {
        self::assertSame('direct', $this->classifier->classify('not-a-url', 'example.com'));
    }
}
