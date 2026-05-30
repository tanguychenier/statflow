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
 * The slice of site + site_settings configuration the ingestion pipeline needs
 * to authorise and filter an event. Built by the SiteRepositoryPort from the
 * `sites` / `site_settings` PostgreSQL tables (postgres-schema.sql §4–5).
 *
 * `trackingEnabled === false` is treated exactly like an unknown key: a
 * disabled site must not accept events (error-catalog.md `invalid-tracker-key`).
 */
final readonly class Site
{
    /**
     * @param list<string>      $allowedDomains    Origins permitted to submit events; empty = allow all.
     * @param list<string>      $excludedIps       Raw IPs whose events are silently dropped.
     * @param list<string>      $excludedCountries ISO-3166-1 alpha-2 codes to drop.
     * @param list<string>|null $customEventNames  Allowlist of `custom` event names; null = allow all.
     */
    public function __construct(
        public string $id,
        public string $trackerKey,
        public bool $trackingEnabled,
        public bool $botFilteringEnabled,
        public array $allowedDomains,
        public array $excludedIps,
        public array $excludedCountries,
        public ?array $customEventNames,
    ) {
    }

    public function allowsAllOrigins(): bool
    {
        return $this->allowedDomains === [];
    }
}
