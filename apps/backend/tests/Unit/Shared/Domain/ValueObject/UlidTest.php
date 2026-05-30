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

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Ulid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ulid::class)]
final class UlidTest extends TestCase
{
    #[Test]
    public function itGeneratesA26CharacterCrockfordBase32Ulid(): void
    {
        $ulid = Ulid::generate();

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid->getValue());
    }

    #[Test]
    public function itEncodesTheSuppliedTimestampInTheTimeComponent(): void
    {
        $first = Ulid::generate(1_000_000_000_000);
        $second = Ulid::generate(2_000_000_000_000);

        self::assertTrue(substr($first->getValue(), 0, 10) < substr($second->getValue(), 0, 10));
    }

    #[Test]
    public function generatedUlidsAreSortableByTime(): void
    {
        $early = Ulid::generate(1_700_000_000_000);
        $late = Ulid::generate(1_700_000_001_000);

        self::assertTrue(strcmp($early->getValue(), $late->getValue()) < 0);
    }

    #[Test]
    public function itAcceptsAValidUlidAndUppercasesIt(): void
    {
        $raw = '01hxk2q4b7t8nmpzw6rdygj5fm';

        self::assertSame(strtoupper($raw), Ulid::fromString($raw)->getValue());
    }

    #[Test]
    #[DataProvider('invalidUlidProvider')]
    public function itRejectsInvalidUlids(string $invalid): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::fromString($invalid);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUlidProvider(): array
    {
        return [
            'too short' => ['01HXK2Q4B7'],
            'too long' => ['01HXK2Q4B7T8NMPZW6RDYGJ5FMZZ'],
            'illegal letter I' => ['01HXK2Q4B7T8NMPZW6RDYGJ5FI'],
            'illegal letter O' => ['01HXK2Q4B7T8NMPZW6RDYGJ5FO'],
            'illegal letter U' => ['01HXK2Q4B7T8NMPZW6RDYGJ5FU'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function itRejectsAnOutOfRangeTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::generate(281474976710656);
    }

    #[Test]
    public function itRejectsANegativeTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::generate(-1);
    }

    #[Test]
    public function twoGeneratedUlidsInTheSameTickDifferInRandomness(): void
    {
        $a = Ulid::generate(1_700_000_000_000);
        $b = Ulid::generate(1_700_000_000_000);

        self::assertNotSame($a->getValue(), $b->getValue());
        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function equalUlidsCompareEqualRegardlessOfCase(): void
    {
        self::assertTrue(
            Ulid::fromString('01hxk2q4b7t8nmpzw6rdygj5fm')->equals(
                Ulid::fromString('01HXK2Q4B7T8NMPZW6RDYGJ5FM'),
            ),
        );
    }

    #[Test]
    public function isValidReflectsAcceptance(): void
    {
        self::assertTrue(Ulid::isValid('01HXK2Q4B7T8NMPZW6RDYGJ5FM'));
        self::assertFalse(Ulid::isValid('nope'));
    }

    #[Test]
    public function toStringReturnsTheValue(): void
    {
        $ulid = Ulid::generate();

        self::assertSame($ulid->getValue(), (string) $ulid);
    }
}
