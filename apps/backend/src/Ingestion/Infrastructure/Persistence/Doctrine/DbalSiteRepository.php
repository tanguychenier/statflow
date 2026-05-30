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

namespace App\Ingestion\Infrastructure\Persistence\Doctrine;

use App\Ingestion\Domain\Model\Site;
use App\Ingestion\Domain\Port\SiteRepositoryPort;
use App\Ingestion\Domain\ValueObject\SiteKey;
use Doctrine\DBAL\Connection;

/**
 * Reads the ingestion-relevant slice of `sites` joined to `site_settings`
 * (postgres-schema.sql §4–5) by the public tracker key. Uses DBAL rather than an
 * ORM entity because the `sites` aggregate is owned by the Identity context;
 * Ingestion only needs a read projection and must not map another context's
 * table.
 *
 * Disabled and soft-deleted sites are filtered out in SQL, so a row returned
 * here is always a live, tracking-enabled site. A small per-request identity-map
 * cache avoids repeated lookups for batches and bursts on the same key.
 */
final class DbalSiteRepository implements SiteRepositoryPort
{
    private const SQL = <<<'SQL'
        SELECT
            s.id::text                        AS id,
            s.tracker_key                     AS tracker_key,
            COALESCE(ss.bot_filtering_enabled, TRUE)  AS bot_filtering_enabled,
            COALESCE(ss.allowed_domains, '[]')        AS allowed_domains,
            COALESCE(ss.excluded_ips, '[]')           AS excluded_ips,
            COALESCE(ss.excluded_countries, '{}')     AS excluded_countries,
            ss.custom_event_names                     AS custom_event_names
        FROM sites s
        LEFT JOIN site_settings ss ON ss.site_id = s.id
        WHERE s.tracker_key = :key
            AND s.tracking_enabled = TRUE
            AND s.deleted_at IS NULL
        LIMIT 1
        SQL;

    /**
     * @var array<string, Site|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function findByKey(SiteKey $key): ?Site
    {
        $value = $key->value();
        if (array_key_exists($value, $this->cache)) {
            return $this->cache[$value];
        }

        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(self::SQL, [
            'key' => $value,
        ]);

        $site = $row === false ? null : $this->hydrate($row);
        $this->cache[$value] = $site;

        return $site;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Site
    {
        $rawCustom = $row['custom_event_names'];
        $customEventNames = $rawCustom === null
            ? null
            : $this->parsePgArray(is_scalar($rawCustom) ? (string) $rawCustom : '');

        return new Site(
            id: is_scalar($row['id']) ? (string) $row['id'] : '',
            trackerKey: is_scalar($row['tracker_key']) ? (string) $row['tracker_key'] : '',
            trackingEnabled: true,
            botFilteringEnabled: (bool) ($row['bot_filtering_enabled'] ?? false),
            allowedDomains: $this->parseJsonArray(is_scalar($row['allowed_domains']) ? (string) $row['allowed_domains'] : '[]'),
            excludedIps: $this->parseJsonArray(is_scalar($row['excluded_ips']) ? (string) $row['excluded_ips'] : '[]'),
            excludedCountries: $this->parsePgArray(is_scalar($row['excluded_countries']) ? (string) $row['excluded_countries'] : '{}'),
            customEventNames: $customEventNames,
        );
    }

    /**
     * Parses a JSONB string-array column (e.g. `["a.com","*.b.com"]`) into a list
     * of strings. `allowed_domains` and `excluded_ips` are stored as JSONB by the
     * Sites aggregate (Version20260516090100), so the ingestion read projection
     * decodes them as JSON rather than as a PostgreSQL array literal.
     *
     * @return list<string>
     */
    private function parseJsonArray(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $decoded),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * Parses a PostgreSQL text-array literal (e.g. `{a.com,*.b.com}`) into a list
     * of strings. DBAL returns array columns as their raw literal on PostgreSQL
     * unless a custom type is registered; parsing here keeps the adapter
     * self-contained.
     *
     * @return list<string>
     */
    private function parsePgArray(string $literal): array
    {
        $trimmed = trim($literal, '{}');
        if ($trimmed === '') {
            return [];
        }

        $values = str_getcsv($trimmed, ',', '"', '\\');

        return array_values(array_filter(
            array_map(static fn (?string $v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
