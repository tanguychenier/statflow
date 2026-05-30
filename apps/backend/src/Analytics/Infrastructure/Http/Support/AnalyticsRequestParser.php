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

namespace App\Analytics\Infrastructure\Http\Support;

use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;

/**
 * Shared parsing helpers for the analytics controllers: decode the JSON body
 * and validate the `{site_id}` path parameter.
 */
final class AnalyticsRequestParser
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the body is not a JSON object
     */
    public function decodeBody(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function siteId(string $raw): Uuid
    {
        return Uuid::fromString($raw);
    }
}
