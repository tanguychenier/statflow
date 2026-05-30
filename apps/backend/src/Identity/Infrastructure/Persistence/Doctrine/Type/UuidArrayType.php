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

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Maps a PostgreSQL `uuid[]` column to a PHP list<string> of UUID strings
 * (ADR-0009: api_keys.site_ids). Reuses the text-array literal encoding; UUIDs
 * are quote-safe so the parent escaping is a harmless no-op.
 */
final class UuidArrayType extends TextArrayType
{
    public const NAME = 'uuid_array';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID[]';
    }
}
