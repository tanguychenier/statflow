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

namespace App\Tests\Unit\Identity\Infrastructure\Persistence;

use App\Identity\Infrastructure\Persistence\Doctrine\Type\TextArrayType;
use App\Identity\Infrastructure\Persistence\Doctrine\Type\UuidArrayType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextArrayType::class)]
#[CoversClass(UuidArrayType::class)]
final class TextArrayTypeTest extends TestCase
{
    private TextArrayType $type;

    private AbstractPlatform&MockObject $platform;

    protected function setUp(): void
    {
        $this->type = new TextArrayType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    #[Test]
    public function nullRoundTripsToNullAndEmptyList(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertSame([], $this->type->convertToPHPValue(null, $this->platform));
    }

    #[Test]
    public function anEmptyArrayEncodesToTheEmptyLiteral(): void
    {
        self::assertSame('{}', $this->type->convertToDatabaseValue([], $this->platform));
        self::assertSame([], $this->type->convertToPHPValue('{}', $this->platform));
    }

    #[Test]
    public function scopeValuesRoundTrip(): void
    {
        $scopes = ['analytics:read', 'reports:write'];

        $encoded = $this->type->convertToDatabaseValue($scopes, $this->platform);
        self::assertIsString($encoded);

        self::assertSame($scopes, $this->type->convertToPHPValue($encoded, $this->platform));
    }

    #[Test]
    public function itParsesAPlainPostgresLiteral(): void
    {
        self::assertSame(
            ['analytics:read', 'sites:read'],
            $this->type->convertToPHPValue('{analytics:read,sites:read}', $this->platform),
        );
    }

    #[Test]
    public function itAcceptsAnAlreadyDecodedArrayFromTheDriver(): void
    {
        self::assertSame(['a', 'b'], $this->type->convertToPHPValue(['a', 'b'], $this->platform));
    }

    #[Test]
    public function theUuidArrayTypeDeclaresUuidColumns(): void
    {
        $uuidType = new UuidArrayType();

        self::assertSame('UUID[]', $uuidType->getSQLDeclaration([], $this->platform));
        self::assertSame('TEXT[]', $this->type->getSQLDeclaration([], $this->platform));
    }

    #[Test]
    public function uuidValuesRoundTrip(): void
    {
        $uuidType = new UuidArrayType();
        $ids = ['3f1a9b2d-2c3a-4b5c-8e9f-0a1b2c3d4e5f'];

        $encoded = $uuidType->convertToDatabaseValue($ids, $this->platform);
        self::assertIsString($encoded);
        self::assertSame($ids, $uuidType->convertToPHPValue($encoded, $this->platform));
    }
}
