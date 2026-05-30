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

namespace App\Ingestion\Domain\Exception;

/**
 * The request `Origin` is not in the site's domain allowlist
 * (error-catalog.md `origin-not-allowed`, HTTP 401).
 */
final class OriginNotAllowed extends IngestionException
{
    public static function forOrigin(string $origin): self
    {
        return new self(sprintf('Origin "%s" is not in the site allowlist.', $origin));
    }

    public function slug(): string
    {
        return 'origin-not-allowed';
    }

    public function title(): string
    {
        return 'Origin Not Allowed';
    }

    public function status(): int
    {
        return 401;
    }
}
