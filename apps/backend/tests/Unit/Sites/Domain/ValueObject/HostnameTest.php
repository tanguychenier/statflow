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

use App\Sites\Domain\Exception\InvalidSiteDomainException;
use App\Sites\Domain\ValueObject\Hostname;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hostname::class)]
#[CoversClass(InvalidSiteDomainException::class)]
final class HostnameTest extends TestCase
{
    #[Test]
    #[DataProvider('validDomains')]
    public function itAcceptsValidDomains(string $input, string $expected): void
    {
        self::assertSame($expected, Hostname::fromString($input)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validDomains(): iterable
    {
        yield 'simple' => ['example.com', 'example.com'];
        yield 'subdomain' => ['stats.example.com', 'stats.example.com'];
        yield 'uppercase normalised' => ['Example.COM', 'example.com'];
        yield 'trimmed' => ['  example.com  ', 'example.com'];
        yield 'hyphenated' => ['my-site.co.uk', 'my-site.co.uk'];
        yield 'localhost' => ['localhost', 'localhost'];
    }

    #[Test]
    #[DataProvider('invalidDomains')]
    public function itRejectsInvalidDomains(string $input): void
    {
        $this->expectException(InvalidSiteDomainException::class);

        Hostname::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
        yield 'with scheme' => ['https://example.com'];
        yield 'with path' => ['example.com/path'];
        yield 'with port' => ['example.com:443'];
        yield 'no tld' => ['example'];
        yield 'single label tld too short' => ['example.c'];
        yield 'trailing dot' => ['example.com.'];
    }

    #[Test]
    public function itRejectsDomainsExceedingMaxLength(): void
    {
        $this->expectException(InvalidSiteDomainException::class);

        Hostname::fromString(str_repeat('a', 250) . '.com');
    }

    #[Test]
    public function itComparesByValue(): void
    {
        self::assertTrue(Hostname::fromString('a.com')->equals(Hostname::fromString('A.com')));
        self::assertFalse(Hostname::fromString('a.com')->equals(Hostname::fromString('b.com')));
        self::assertSame('a.com', (string) Hostname::fromString('a.com'));
    }
}
