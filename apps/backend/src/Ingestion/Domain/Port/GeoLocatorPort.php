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

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Model\GeoLocation;

/**
 * Driven port: resolve a client IP to country/region/city using the embedded
 * geo database (architecture.md §"100% Local"). Implementations must never make
 * a network call and must return GeoLocation::unknown() on a miss.
 */
interface GeoLocatorPort
{
    public function locate(string $ipAddress): GeoLocation;
}
