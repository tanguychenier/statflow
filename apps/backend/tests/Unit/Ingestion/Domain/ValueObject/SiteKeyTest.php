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

namespace App\Tests\Unit\Ingestion\Domain\ValueObject;

use App\Ingestion\Domain\Exception\InvalidTrackerKey;
use App\Ingestion\Domain\ValueObject\SiteKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteKey::class)]
#[CoversClass(InvalidTrackerKey::class)]
final class SiteKeyTest extends TestCase
{
    #[Test]
    public function itAcceptsAWellFormedKey(): void
    {
        $key = SiteKey::fromString('stk_abcdef1234567890');

        self::assertSame('stk_abcdef1234567890', $key->value());
    }

    #[Test]
    #[DataProvider('malformedKeys')]
    public function itRejectsMalformedKeys(string $value): void
    {
        $this->expectException(InvalidTrackerKey::class);

        SiteKey::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedKeys(): iterable
    {
        yield 'no prefix' => ['abcdef1234567890'];
        yield 'wrong prefix' => ['sfk_abcdef1234567890'];
        yield 'too short' => ['stk_short'];
        yield 'illegal chars' => ['stk_abc!@#$%^&*()_+'];
        yield 'empty' => [''];
    }

    #[Test]
    public function equalsUsesConstantTimeComparison(): void
    {
        $a = SiteKey::fromString('stk_abcdef1234567890');
        $b = SiteKey::fromString('stk_abcdef1234567890');
        $c = SiteKey::fromString('stk_zzzzzz9999999999');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function malformedKeyRaises401(): void
    {
        try {
            SiteKey::fromString('bad');
            self::fail('Expected InvalidTrackerKey.');
        } catch (InvalidTrackerKey $e) {
            self::assertSame(401, $e->status());
            self::assertSame('invalid-tracker-key', $e->slug());
            self::assertSame('https://statflow.io/errors/invalid-tracker-key', $e->type());
        }
    }
}
