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

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Doctrine\Type\UuidType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidType::class)]
final class UuidTypeTest extends TestCase
{
    private const SAMPLE = '550e8400-e29b-41d4-a716-446655440000';

    private UuidType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UuidType();
        $this->platform = new PostgreSQLPlatform();
    }

    #[Test]
    public function itDeclaresAGuidColumn(): void
    {
        self::assertSame('UUID', $this->type->getSQLDeclaration([], $this->platform));
    }

    #[Test]
    public function itConvertsAValueObjectToItsStringForm(): void
    {
        $value = Uuid::fromString(self::SAMPLE);

        self::assertSame(self::SAMPLE, $this->type->convertToDatabaseValue($value, $this->platform));
    }

    #[Test]
    public function itConvertsAValidStringToDatabaseValue(): void
    {
        self::assertSame(self::SAMPLE, $this->type->convertToDatabaseValue(self::SAMPLE, $this->platform));
    }

    #[Test]
    public function itPassesNullThroughBothDirections(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    #[Test]
    public function itHydratesAStringIntoAValueObject(): void
    {
        $uuid = $this->type->convertToPHPValue(self::SAMPLE, $this->platform);

        self::assertInstanceOf(Uuid::class, $uuid);
        self::assertSame(self::SAMPLE, $uuid->getValue());
    }

    #[Test]
    public function itReturnsAnAlreadyHydratedValueObjectUnchanged(): void
    {
        $uuid = Uuid::fromString(self::SAMPLE);

        self::assertSame($uuid, $this->type->convertToPHPValue($uuid, $this->platform));
    }

    #[Test]
    public function itRejectsAMalformedDatabaseStringOnWrite(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToDatabaseValue('not-a-uuid', $this->platform);
    }

    #[Test]
    public function itRejectsAMalformedStringOnRead(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToPHPValue('not-a-uuid', $this->platform);
    }

    #[Test]
    public function itRejectsANonStringDatabaseValue(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(42, $this->platform);
    }

    #[Test]
    public function itRejectsANonStringPhpValue(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToPHPValue(42, $this->platform);
    }
}
