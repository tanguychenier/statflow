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

use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
    #[Test]
    public function itGeneratesAValidUuidV4(): void
    {
        $uuid = Uuid::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->getValue(),
        );
    }

    #[Test]
    public function itAcceptsAValidUuidString(): void
    {
        $raw = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($raw);

        self::assertSame($raw, $uuid->getValue());
    }

    #[Test]
    public function itNormalisesUuidToLowercase(): void
    {
        $upper = '550E8400-E29B-41D4-A716-446655440000';
        $uuid = Uuid::fromString($upper);

        self::assertSame(strtolower($upper), $uuid->getValue());
    }

    #[Test]
    #[DataProvider('invalidUuidProvider')]
    public function itRejectsInvalidUuids(string $invalid): void
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::fromString($invalid);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidUuidProvider(): array
    {
        return [
            ['not-a-uuid'],
            ['550e8400-e29b-41d4-a716'],
            [''],
            ['00000000-0000-0000-0000-00000000000g'],
        ];
    }

    #[Test]
    public function twoGeneratedUuidsAreNotEqual(): void
    {
        $a = Uuid::generate();
        $b = Uuid::generate();

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function uuidWithSameValueAreEqual(): void
    {
        $raw = '550e8400-e29b-41d4-a716-446655440000';

        self::assertTrue(Uuid::fromString($raw)->equals(Uuid::fromString($raw)));
    }

    #[Test]
    public function toStringReturnsTheValue(): void
    {
        $raw = '550e8400-e29b-41d4-a716-446655440000';

        self::assertSame($raw, (string) Uuid::fromString($raw));
    }
}
