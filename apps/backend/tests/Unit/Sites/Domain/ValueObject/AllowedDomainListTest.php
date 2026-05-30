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

use App\Sites\Domain\Exception\InvalidAllowedDomainException;
use App\Sites\Domain\ValueObject\AllowedDomainList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowedDomainList::class)]
#[CoversClass(InvalidAllowedDomainException::class)]
final class AllowedDomainListTest extends TestCase
{
    #[Test]
    public function itAcceptsHostsWildcardsAndLocalhost(): void
    {
        $list = AllowedDomainList::fromArray([
            'example.com',
            '*.example.com',
            'localhost',
        ]);

        self::assertSame(['example.com', '*.example.com', 'localhost'], $list->toArray());
        self::assertFalse($list->isEmpty());
    }

    #[Test]
    public function itLowercasesTrimsAndDeduplicates(): void
    {
        $list = AllowedDomainList::fromArray([' Example.COM ', 'example.com', '']);

        self::assertSame(['example.com'], $list->toArray());
    }

    #[Test]
    public function emptyMeansAllowAll(): void
    {
        self::assertTrue(AllowedDomainList::empty()->isEmpty());
        self::assertTrue(AllowedDomainList::fromArray([])->isEmpty());
    }

    #[Test]
    #[DataProvider('invalidPatterns')]
    public function itRejectsMalformedPatterns(string $pattern): void
    {
        $this->expectException(InvalidAllowedDomainException::class);

        AllowedDomainList::fromArray([$pattern]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPatterns(): iterable
    {
        yield 'scheme' => ['https://example.com'];
        yield 'double wildcard' => ['*.*.example.com'];
        yield 'wildcard without host' => ['*.'];
        yield 'no tld' => ['example'];
        yield 'path' => ['example.com/x'];
    }

    #[Test]
    public function itRejectsTooManyEntries(): void
    {
        $entries = [];
        for ($i = 0; $i < AllowedDomainList::MAX_ENTRIES + 1; ++$i) {
            $entries[] = 'site' . $i . '.example.com';
        }

        $this->expectException(InvalidAllowedDomainException::class);

        AllowedDomainList::fromArray($entries);
    }
}
