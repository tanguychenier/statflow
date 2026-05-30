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

namespace App\Ingestion\Domain\Model;

/**
 * Geo fields resolved at enrichment time from the embedded geo database
 * (architecture.md §"100% Local"). The raw IP is never stored; only these
 * derived fields are (identity-and-privacy.md §7).
 */
final readonly class GeoLocation
{
    public function __construct(
        public string $countryCode,
        public string $region,
        public string $city,
    ) {
    }

    public static function unknown(): self
    {
        return new self('', '', '');
    }
}
