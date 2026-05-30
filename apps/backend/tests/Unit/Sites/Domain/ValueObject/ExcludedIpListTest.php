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

use App\Sites\Domain\Exception\InvalidExcludedIpException;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExcludedIpList::class)]
#[CoversClass(InvalidExcludedIpException::class)]
final class ExcludedIpListTest extends TestCase
{
    #[Test]
    public function itAcceptsIpv4Ipv6AndCidr(): void
    {
        $list = ExcludedIpList::fromArray([
            '192.168.0.1',
            '10.0.0.0/8',
            '2001:db8::1',
            '2001:db8::/32',
        ]);

        self::assertCount(4, $list->toArray());
        self::assertTrue($list->contains('192.168.0.1'));
        self::assertFalse($list->isEmpty());
    }

    #[Test]
    public function itTrimsDeduplicatesAndSkipsEmpty(): void
    {
        $list = ExcludedIpList::fromArray([' 1.1.1.1 ', '1.1.1.1', '', '   ']);

        self::assertSame(['1.1.1.1'], $list->toArray());
    }

    #[Test]
    public function emptyListIsEmpty(): void
    {
        self::assertTrue(ExcludedIpList::empty()->isEmpty());
        self::assertSame([], ExcludedIpList::empty()->toArray());
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function itRejectsMalformedEntries(string $entry): void
    {
        $this->expectException(InvalidExcludedIpException::class);

        ExcludedIpList::fromArray([$entry]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'not an ip' => ['not-an-ip'];
        yield 'cidr missing prefix' => ['10.0.0.0/'];
        yield 'cidr non numeric prefix' => ['10.0.0.0/x'];
        yield 'cidr prefix too large v4' => ['10.0.0.0/33'];
        yield 'cidr prefix too large v6' => ['2001:db8::/129'];
        yield 'bad address in cidr' => ['999.0.0.0/8'];
    }

    #[Test]
    public function itRejectsTooManyEntries(): void
    {
        $entries = [];
        for ($i = 0; $i < ExcludedIpList::MAX_ENTRIES + 1; ++$i) {
            $entries[] = '10.0.' . intdiv($i, 256) . '.' . ($i % 256);
        }

        $this->expectException(InvalidExcludedIpException::class);

        ExcludedIpList::fromArray($entries);
    }
}
