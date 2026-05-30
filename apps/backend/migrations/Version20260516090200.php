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
 * Analytics + Reporting schema.
 *
 * Analytics owns goals, funnels, funnel steps and segments as DBAL read/write
 * projections (postgres-schema.sql §7–9); `segments.filter_combination` is the
 * boolean-combination column the analytics query layer reads. Reporting owns
 * saved reports, scheduled reports, alerts and exports as Doctrine ORM entities
 * (App\Reporting\Domain\Model). All site-scoped tables cascade from `sites`.
 */
final class Version20260516090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Analytics (goals, funnels, segments) and Reporting (saved_reports, scheduled_reports, alerts, exports).';
    }

    public function up(Schema $schema): void
    {
        // ── Analytics ───────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE goals (
                id            UUID         NOT NULL,
                site_id       UUID         NOT NULL,
                name          VARCHAR(255) NOT NULL,
                trigger_type  VARCHAR(16)  NOT NULL,
                url_pattern   TEXT             NULL,
                event_name    VARCHAR(255)     NULL,
                currency      CHAR(3)          NULL,
                revenue_value NUMERIC(18, 4)   NULL,
                created_at    TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at    TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at    TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT goals_trigger_type CHECK (trigger_type IN ('pageview', 'event')),
                CONSTRAINT goals_trigger_check CHECK (
                    (trigger_type = 'pageview' AND url_pattern IS NOT NULL AND event_name IS NULL)
                    OR (trigger_type = 'event' AND event_name IS NOT NULL AND url_pattern IS NULL)
                )
            )
            SQL);
        $this->addSql('CREATE INDEX goals_site_id ON goals (site_id) WHERE deleted_at IS NULL');
        $this->addSql('ALTER TABLE goals ADD CONSTRAINT fk_goals_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE funnels (
                id         UUID         NOT NULL,
                site_id    UUID         NOT NULL,
                name       VARCHAR(255) NOT NULL,
                created_by UUID             NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX funnels_site_id ON funnels (site_id) WHERE deleted_at IS NULL');
        $this->addSql('ALTER TABLE funnels ADD CONSTRAINT fk_funnels_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE funnels ADD CONSTRAINT fk_funnels_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE funnel_steps (
                id           UUID         NOT NULL,
                funnel_id    UUID         NOT NULL,
                goal_id      UUID             NULL,
                step_index   SMALLINT     NOT NULL,
                name         VARCHAR(255) NOT NULL,
                trigger_type VARCHAR(16)  NOT NULL,
                url_pattern  TEXT             NULL,
                event_name   VARCHAR(255)     NULL,
                PRIMARY KEY (id),
                CONSTRAINT funnel_steps_step_index CHECK (step_index >= 0),
                CONSTRAINT funnel_steps_unique UNIQUE (funnel_id, step_index),
                CONSTRAINT funnel_steps_trigger_type CHECK (trigger_type IN ('pageview', 'event')),
                CONSTRAINT funnel_steps_trigger_check CHECK (
                    goal_id IS NOT NULL OR url_pattern IS NOT NULL OR event_name IS NOT NULL
                )
            )
            SQL);
        $this->addSql('CREATE INDEX funnel_steps_funnel_id ON funnel_steps (funnel_id)');
        $this->addSql('CREATE INDEX funnel_steps_goal_id ON funnel_steps (goal_id)');
        $this->addSql('ALTER TABLE funnel_steps ADD CONSTRAINT fk_funnel_steps_funnel FOREIGN KEY (funnel_id) REFERENCES funnels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE funnel_steps ADD CONSTRAINT fk_funnel_steps_goal FOREIGN KEY (goal_id) REFERENCES goals (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE segments (
                id                 UUID         NOT NULL,
                site_id            UUID         NOT NULL,
                name               VARCHAR(255) NOT NULL,
                color              CHAR(7)      NOT NULL DEFAULT '#6366F1',
                filters            JSONB        NOT NULL DEFAULT '[]',
                filter_combination VARCHAR(8)   NOT NULL DEFAULT 'and',
                scope              VARCHAR(16)  NOT NULL DEFAULT 'private',
                created_by         UUID             NULL,
                created_at         TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at         TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at         TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT segments_scope CHECK (scope IN ('private', 'shared')),
                CONSTRAINT segments_filter_combination CHECK (filter_combination IN ('and', 'or'))
            )
            SQL);
        $this->addSql('CREATE INDEX segments_site_id ON segments (site_id) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX segments_filters ON segments USING GIN (filters)');
        $this->addSql('ALTER TABLE segments ADD CONSTRAINT fk_segments_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE segments ADD CONSTRAINT fk_segments_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        // ── Reporting ─────────────────────────────────────────────────────────—
        $this->addSql(<<<'SQL'
            CREATE TABLE saved_reports (
                id          UUID         NOT NULL,
                site_id     UUID         NOT NULL,
                name        VARCHAR(255) NOT NULL,
                description TEXT             NULL,
                report_type VARCHAR(32)  NOT NULL,
                definition  JSONB        NOT NULL DEFAULT '{}',
                created_by  UUID             NULL,
                created_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at  TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX saved_reports_site_id ON saved_reports (site_id) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX saved_reports_keyset ON saved_reports (site_id, created_at DESC, id DESC)');
        $this->addSql('ALTER TABLE saved_reports ADD CONSTRAINT fk_saved_reports_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE saved_reports ADD CONSTRAINT fk_saved_reports_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE scheduled_reports (
                id              UUID         NOT NULL,
                site_id         UUID         NOT NULL,
                saved_report_id UUID             NULL,
                name            VARCHAR(255) NOT NULL,
                recipients      JSONB        NOT NULL DEFAULT '[]',
                schedule_cron   VARCHAR(100) NOT NULL,
                timezone        VARCHAR(64)  NOT NULL DEFAULT 'UTC',
                is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
                created_by      UUID             NULL,
                last_sent_at    TIMESTAMP(0) WITH TIME ZONE NULL,
                next_send_at    TIMESTAMP(0) WITH TIME ZONE NULL,
                created_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at      TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX scheduled_reports_site_id ON scheduled_reports (site_id) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX scheduled_reports_due ON scheduled_reports (next_send_at) WHERE is_active = TRUE AND deleted_at IS NULL');
        $this->addSql('ALTER TABLE scheduled_reports ADD CONSTRAINT fk_scheduled_reports_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scheduled_reports ADD CONSTRAINT fk_scheduled_reports_saved_report FOREIGN KEY (saved_report_id) REFERENCES saved_reports (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE scheduled_reports ADD CONSTRAINT fk_scheduled_reports_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE alerts (
                id                    UUID          NOT NULL,
                site_id               UUID          NOT NULL,
                name                  VARCHAR(255)  NOT NULL,
                created_by            UUID              NULL,
                metric                VARCHAR(32)   NOT NULL,
                condition             VARCHAR(32)   NOT NULL,
                threshold             NUMERIC(18, 4) NOT NULL,
                comparison_period     VARCHAR(32)       NULL,
                filters               JSONB         NOT NULL DEFAULT '[]',
                notification_channels JSONB         NOT NULL DEFAULT '[]',
                is_active             BOOLEAN       NOT NULL DEFAULT TRUE,
                last_triggered_at     TIMESTAMP(0) WITH TIME ZONE NULL,
                created_at            TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at            TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at            TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX alerts_site_id ON alerts (site_id) WHERE deleted_at IS NULL AND is_active = TRUE');
        $this->addSql('ALTER TABLE alerts ADD CONSTRAINT fk_alerts_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerts ADD CONSTRAINT fk_alerts_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE exports (
                id              UUID         NOT NULL,
                site_id         UUID         NOT NULL,
                status          VARCHAR(16)  NOT NULL,
                format          VARCHAR(16)  NOT NULL,
                query           JSONB        NOT NULL DEFAULT '{}',
                notify_email    VARCHAR(254)     NULL,
                created_by      UUID             NULL,
                row_count       INTEGER          NULL,
                file_size_bytes BIGINT           NULL,
                artifact_key    VARCHAR(512)     NULL,
                expires_at      TIMESTAMP(0) WITH TIME ZONE NULL,
                error_message   TEXT             NULL,
                created_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                completed_at    TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX exports_site_id ON exports (site_id)');
        $this->addSql('ALTER TABLE exports ADD CONSTRAINT fk_exports_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exports ADD CONSTRAINT fk_exports_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS exports');
        $this->addSql('DROP TABLE IF EXISTS alerts');
        $this->addSql('DROP TABLE IF EXISTS scheduled_reports');
        $this->addSql('DROP TABLE IF EXISTS saved_reports');
        $this->addSql('DROP TABLE IF EXISTS segments');
        $this->addSql('DROP TABLE IF EXISTS funnel_steps');
        $this->addSql('DROP TABLE IF EXISTS funnels');
        $this->addSql('DROP TABLE IF EXISTS goals');
    }
}
