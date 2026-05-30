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

namespace App\Ingestion\Infrastructure\Geo;

use App\Ingestion\Domain\Model\GeoLocation;
use App\Ingestion\Domain\Port\GeoLocatorPort;

/**
 * Safe default geo locator: resolves nothing. Used when no embedded geo database
 * is mounted so ingestion still works (events store empty geo) without ever
 * making a network call.
 */
final class NullGeoLocator implements GeoLocatorPort
{
    public function locate(string $ipAddress): GeoLocation
    {
        return GeoLocation::unknown();
    }
}
