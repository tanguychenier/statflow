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

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sites context schema: a tracked web property and its one-to-one settings
 * (postgres-schema.sql §4–5, ADR-0009).
 *
 * The `sites` / `site_settings` columns follow the Sites aggregate in
 * App\Sites\Domain\Model (the owning writer; persistence mapped via XML under
 * Infrastructure). `site_settings` additionally
 * carries the read-only ingestion columns (bot filtering, country / IP
 * exclusions, custom-event allowlist) consumed by the Ingestion read projection
 * (DbalSiteRepository).
 */
final class Version20260516090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sites: sites and site_settings (write model + ingestion read columns).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        $this->addSql(<<<'SQL'
            CREATE TABLE sites (
                id                                UUID         NOT NULL,
                team_id                           UUID         NOT NULL,
                name                              VARCHAR(255) NOT NULL,
                domain                            VARCHAR(253) NOT NULL,
                timezone                          VARCHAR(64)  NOT NULL DEFAULT 'UTC',
                tracking_enabled                  BOOLEAN      NOT NULL DEFAULT TRUE,
                tracker_key                       TEXT         NOT NULL,
                retention_days                    INTEGER          NULL,
                created_at                        TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at                        TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at                        TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT sites_retention_days_range CHECK (retention_days IS NULL OR retention_days BETWEEN 30 AND 730)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX sites_domain_team_unique ON sites (team_id, domain) WHERE deleted_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX sites_tracker_key_unique ON sites (tracker_key)');
        $this->addSql('CREATE INDEX sites_team_id ON sites (team_id)');
        $this->addSql('CREATE INDEX sites_domain ON sites (domain)');
        $this->addSql('CREATE INDEX sites_name_trgm ON sites USING GIN (name gin_trgm_ops)');
        $this->addSql('ALTER TABLE sites ADD CONSTRAINT fk_sites_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE site_settings (
                site_id                 UUID         NOT NULL,
                allowed_domains         JSONB        NOT NULL DEFAULT '[]',
                excluded_ips            JSONB        NOT NULL DEFAULT '[]',
                strip_query_params      BOOLEAN      NOT NULL DEFAULT FALSE,
                custom_domain_enabled   BOOLEAN      NOT NULL DEFAULT FALSE,
                track_clicks            BOOLEAN      NOT NULL DEFAULT TRUE,
                track_scroll            BOOLEAN      NOT NULL DEFAULT TRUE,
                track_engagement_time   BOOLEAN      NOT NULL DEFAULT TRUE,
                track_outbound_links    BOOLEAN      NOT NULL DEFAULT TRUE,
                hash_based_routing      BOOLEAN      NOT NULL DEFAULT FALSE,
                ignored_selectors       JSONB        NOT NULL DEFAULT '[]',
                sampling_rate           NUMERIC(4, 3) NOT NULL DEFAULT 1.000,
                script_variant          VARCHAR(32)  NOT NULL DEFAULT 'default',
                bot_filtering_enabled   BOOLEAN      NOT NULL DEFAULT TRUE,
                excluded_countries      CHAR(2)[]    NOT NULL DEFAULT '{}',
                custom_event_names      TEXT[]           NULL,
                updated_at              TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (site_id),
                CONSTRAINT site_settings_sampling_rate_range CHECK (sampling_rate BETWEEN 0.000 AND 1.000),
                CONSTRAINT site_settings_script_variant CHECK (script_variant IN ('default', 'compat', 'manual', 'entropy'))
            )
            SQL);
        $this->addSql('ALTER TABLE site_settings ADD CONSTRAINT fk_site_settings_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS site_settings');
        $this->addSql('DROP TABLE IF EXISTS sites');
    }
}
