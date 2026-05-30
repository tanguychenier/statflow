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

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

/**
 * Doctrine DBAL type mapping the {@see Uuid} value object to a PostgreSQL `uuid`
 * column. Entities in the Postgres contexts type their identifier properties as
 * {@see Uuid} and declare `type: UuidType::NAME` on the mapping.
 *
 * Registered under the name `statflow_uuid` by the wiring agent's Doctrine config.
 */
final class UuidType extends Type
{
    public const NAME = 'statflow_uuid';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Uuid) {
            return $value->getValue();
        }

        if (is_string($value)) {
            try {
                return Uuid::fromString($value)->getValue();
            } catch (InvalidArgumentException $exception) {
                throw InvalidFormat::new($value, self::NAME, '36-character UUID', $exception);
            }
        }

        throw InvalidType::new($value, self::NAME, ['null', 'string', Uuid::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value === null || $value instanceof Uuid) {
            return $value;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', Uuid::class]);
        }

        try {
            return Uuid::fromString($value);
        } catch (InvalidArgumentException $exception) {
            throw InvalidFormat::new($value, self::NAME, '36-character UUID', $exception);
        }
    }
}
