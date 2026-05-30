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

use App\Sites\Domain\Exception\InvalidTrackerKeyException;
use App\Sites\Domain\ValueObject\TrackerKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrackerKey::class)]
#[CoversClass(InvalidTrackerKeyException::class)]
final class TrackerKeyTest extends TestCase
{
    #[Test]
    public function itAcceptsAWellFormedKey(): void
    {
        $key = TrackerKey::fromString('stk_' . str_repeat('a', 32));

        self::assertStringStartsWith('stk_', $key->value());
        self::assertSame('stk_' . str_repeat('a', 32), (string) $key);
    }

    #[Test]
    public function itAcceptsUrlSafeSuffixCharacters(): void
    {
        $key = TrackerKey::fromString('stk_' . str_repeat('aB9-_', 6) . 'ab');

        self::assertStringStartsWith('stk_', $key->value());
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function itRejectsMalformedKeys(string $input): void
    {
        $this->expectException(InvalidTrackerKeyException::class);

        TrackerKey::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'no prefix' => [str_repeat('a', 32)];
        yield 'wrong prefix' => ['sfk_' . str_repeat('a', 32)];
        yield 'suffix too short' => ['stk_' . str_repeat('a', 31)];
        yield 'suffix too long' => ['stk_' . str_repeat('a', 65)];
        yield 'illegal char' => ['stk_' . str_repeat('a', 31) . '!'];
        yield 'empty' => [''];
    }

    #[Test]
    public function itComparesInConstantTime(): void
    {
        $a = TrackerKey::fromString('stk_' . str_repeat('a', 32));
        $b = TrackerKey::fromString('stk_' . str_repeat('a', 32));
        $c = TrackerKey::fromString('stk_' . str_repeat('b', 32));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
