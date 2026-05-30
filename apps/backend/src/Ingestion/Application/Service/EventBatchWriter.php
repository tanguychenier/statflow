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

namespace App\Ingestion\Application\Service;

use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Port\EventWriterPort;
use App\Ingestion\Domain\Port\SiteRepositoryPort;
use App\Ingestion\Domain\ValueObject\SiteKey;

/**
 * The batch-writer use case: enrich a slice of buffered events and bulk-insert
 * them into ClickHouse (architecture.md §"Batch Writer Worker").
 *
 * Country exclusion (site_settings.excluded_countries) is applied here because
 * the country is only known after geo resolution. IP exclusion was already
 * applied on the hot path; this is the geo-derived second gate.
 */
final readonly class EventBatchWriter
{
    public function __construct(
        private EventEnricher $enricher,
        private EventWriterPort $writer,
        private SiteRepositoryPort $sites,
    ) {
    }

    /**
     * @param list<BufferedEvent> $bufferedEvents
     */
    public function write(array $bufferedEvents): void
    {
        $rows = [];
        $excludedCountries = $this->excludedCountriesBySite($bufferedEvents);

        foreach ($bufferedEvents as $buffered) {
            $enriched = $this->enricher->enrich($buffered);

            if ($this->isExcludedCountry($enriched, $excludedCountries)) {
                continue;
            }

            $rows[] = $enriched;
        }

        if ($rows !== []) {
            $this->writer->writeBatch($rows);
        }
    }

    /**
     * @param list<BufferedEvent> $bufferedEvents
     *
     * @return array<string, list<string>> siteId => excluded ISO-3166 alpha-2 codes
     */
    private function excludedCountriesBySite(array $bufferedEvents): array
    {
        $map = [];
        foreach ($bufferedEvents as $buffered) {
            if (array_key_exists($buffered->siteId, $map)) {
                continue;
            }

            $site = $this->sites->findByKey(SiteKey::fromString($buffered->event->siteKey));
            $map[$buffered->siteId] = $site !== null ? $site->excludedCountries : [];
        }

        return $map;
    }

    /**
     * @param array<string, list<string>> $excludedCountries
     */
    private function isExcludedCountry(EnrichedEvent $event, array $excludedCountries): bool
    {
        $codes = $excludedCountries[$event->siteId] ?? [];
        if ($codes === [] || $event->geo->countryCode === '') {
            return false;
        }

        return in_array(strtoupper($event->geo->countryCode), array_map(strtoupper(...), $codes), true);
    }
}
