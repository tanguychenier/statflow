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

namespace App\Tests\Ingestion\Support;

use App\Ingestion\Domain\Model\GeoLocation;
use App\Ingestion\Domain\Port\GeoLocatorPort;

/**
 * Stub GeoLocatorPort returning a configurable location per IP.
 */
final class StubGeoLocator implements GeoLocatorPort
{
    /**
     * @var array<string, GeoLocation>
     */
    private array $byIp = [];

    public function set(string $ip, GeoLocation $location): void
    {
        $this->byIp[$ip] = $location;
    }

    public function locate(string $ipAddress): GeoLocation
    {
        return $this->byIp[$ipAddress] ?? GeoLocation::unknown();
    }
}
