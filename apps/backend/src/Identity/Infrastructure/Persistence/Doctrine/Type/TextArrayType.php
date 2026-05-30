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
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps a PostgreSQL `text[]` column to a PHP list<string> (ADR-0009: api_keys
 * stores scopes as a native TEXT[] array, not JSON). Encodes to the PostgreSQL
 * array literal `{a,b,c}` and parses it back. Each element is a member of a
 * fixed, alphanumeric+colon vocabulary, so quoting/escaping is unnecessary.
 */
class TextArrayType extends Type
{
    public const NAME = 'text_array';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT[]';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidType::new($value, self::NAME, ['array', 'null']);
        }

        if ($value === []) {
            return '{}';
        }

        /** @var list<string> $stringValue */
        $stringValue = array_values($value);
        $escaped = array_map(
            static fn (string $item): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $item) . '"',
            $stringValue,
        );

        return '{' . implode(',', $escaped) . '}';
    }

    /**
     * @return list<string>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null || $value === '' || $value === '{}') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value));
        }

        if (!is_scalar($value)) {
            return [];
        }

        $inner = trim((string) $value, '{}');

        if ($inner === '') {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => is_string($item) ? stripslashes(trim($item, '"')) : '',
            str_getcsv($inner, ',', '"', '\\'),
        ));
    }
}
