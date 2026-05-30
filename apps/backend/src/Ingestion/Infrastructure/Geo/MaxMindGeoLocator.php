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
use MaxMind\Db\Reader;
use Throwable;

/**
 * Resolves geo fields from an embedded MaxMind-format `.mmdb` city database
 * shipped in the backend image (architecture.md §"100% Local"). The reader does
 * pure local file lookups — there is no network call, satisfying the
 * zero-external-runtime-call rule.
 *
 * The MaxMind\Db\Reader is resolved dynamically: the geo reader is an optional
 * deployment dependency (the `maxmind-db/reader` package plus a mounted
 * database). When it is absent the locator degrades to GeoLocation::unknown()
 * instead of failing the write, so ingestion works on a minimal install.
 */
final class MaxMindGeoLocator implements GeoLocatorPort
{
    private const READER_CLASS = Reader::class;

    private ?object $reader = null;

    private bool $readerResolved = false;

    public function __construct(
        private readonly string $databasePath,
        private readonly string $locale = 'en',
    ) {
    }

    public function locate(string $ipAddress): GeoLocation
    {
        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return GeoLocation::unknown();
        }

        $reader = $this->reader();
        if ($reader === null) {
            return GeoLocation::unknown();
        }

        try {
            /** @var mixed $record */
            $record = method_exists($reader, 'get') ? $reader->get($ipAddress) : null;
        } catch (Throwable) {
            return GeoLocation::unknown();
        }

        if (!is_array($record)) {
            return GeoLocation::unknown();
        }

        /** @var array<string, mixed> $record */
        $city = $record['city'] ?? null;
        $cityNames = is_array($city) ? ($city['names'] ?? null) : null;

        return new GeoLocation(
            countryCode: $this->countryCode($record),
            region: $this->region($record),
            city: $this->localized($cityNames),
        );
    }

    private function reader(): ?object
    {
        if ($this->readerResolved) {
            return $this->reader;
        }

        $this->readerResolved = true;

        $readerClass = self::READER_CLASS;
        if (!class_exists($readerClass) || !is_file($this->databasePath)) {
            return $this->reader = null;
        }

        try {
            return $this->reader = new $readerClass($this->databasePath);
        } catch (Throwable) {
            return $this->reader = null;
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function countryCode(array $record): string
    {
        $country = $record['country'] ?? null;
        $registered = $record['registered_country'] ?? null;
        $code = (is_array($country) ? ($country['iso_code'] ?? null) : null)
            ?? (is_array($registered) ? ($registered['iso_code'] ?? null) : null)
            ?? '';

        return is_string($code) ? strtoupper($code) : '';
    }

    /**
     * @param array<string, mixed> $record
     */
    private function region(array $record): string
    {
        $subdivisions = $record['subdivisions'] ?? null;
        if (is_array($subdivisions) && isset($subdivisions[0]) && is_array($subdivisions[0])) {
            return $this->localized($subdivisions[0]['names'] ?? null);
        }

        return '';
    }

    private function localized(mixed $names): string
    {
        if (!is_array($names)) {
            return '';
        }

        $value = $names[$this->locale] ?? $names['en'] ?? '';

        return is_string($value) ? $value : '';
    }
}
