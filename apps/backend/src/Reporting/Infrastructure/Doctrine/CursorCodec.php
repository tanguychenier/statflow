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

namespace App\Reporting\Infrastructure\Doctrine;

use DateTimeImmutable;

/**
 * Opaque keyset-cursor encoding shared by the reporting Doctrine repositories.
 *
 * A cursor packs the last row's (created_at, id) pair, which together form the
 * stable sort key for the (created_at DESC, id DESC) ordering used everywhere in
 * this context. Base64 keeps the token URL-safe and opaque to clients.
 */
trait CursorCodec
{
    private function encodeCursor(DateTimeImmutable $createdAt, string $id): string
    {
        return base64_encode($createdAt->format('Y-m-d H:i:s.u') . '|' . $id);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null) {
            return null;
        }

        $decoded = base64_decode($cursor, true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            return null;
        }

        [$createdAt, $id] = explode('|', $decoded, 2);

        if ($createdAt === '' || $id === '') {
            return null;
        }

        return [$createdAt, $id];
    }
}
