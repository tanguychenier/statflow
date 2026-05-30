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
 * Identity context schema: users, teams, team members, API keys, the audit log,
 * and single-use password-reset tokens (postgres-schema.sql §1–3, §6, §12,
 * ADR-0009).
 *
 * Tables map to the Doctrine ORM entities in App\Identity\Domain\Model. UUID
 * primary keys are generated application-side (Uuid value object), so columns are
 * plain UUID with no database default. Soft-deletable tables carry a partial
 * unique index so a freed natural key (email / slug) can be reused.
 */
final class Version20260516090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: users, teams, team_members, api_keys, audit_log, password_reset_tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'This migration targets PostgreSQL.',
        );

        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id              UUID         NOT NULL,
                email           VARCHAR(254) NOT NULL,
                name            VARCHAR(255) NOT NULL,
                avatar_url      TEXT             NULL,
                password_hash   TEXT             NULL,
                email_verified  BOOLEAN      NOT NULL DEFAULT FALSE,
                last_login_at   TIMESTAMP(0) WITH TIME ZONE NULL,
                timezone        VARCHAR(64)  NOT NULL DEFAULT 'UTC',
                locale          VARCHAR(10)  NOT NULL DEFAULT 'en',
                created_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at      TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX users_email_unique ON users (LOWER(email)) WHERE deleted_at IS NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE teams (
                id                  UUID         NOT NULL,
                name                VARCHAR(255) NOT NULL,
                slug                VARCHAR(63)  NOT NULL,
                owner_id            UUID         NOT NULL,
                is_personal         BOOLEAN      NOT NULL DEFAULT FALSE,
                plan                VARCHAR(32)  NOT NULL DEFAULT 'free',
                plan_expires_at     TIMESTAMP(0) WITH TIME ZONE NULL,
                stripe_customer_id  TEXT             NULL,
                monthly_event_quota BIGINT       NOT NULL DEFAULT 100000,
                monthly_event_used  BIGINT       NOT NULL DEFAULT 0,
                created_at          TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at          TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                deleted_at          TIMESTAMP(0) WITH TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX teams_slug_unique ON teams (slug) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX teams_owner_id ON teams (owner_id)');
        $this->addSql('ALTER TABLE teams ADD CONSTRAINT fk_teams_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT');

        $this->addSql(<<<'SQL'
            CREATE TABLE team_members (
                id          UUID        NOT NULL,
                team_id     UUID        NOT NULL,
                user_id     UUID        NOT NULL,
                role        VARCHAR(16) NOT NULL DEFAULT 'viewer',
                invited_by  UUID            NULL,
                invited_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                accepted_at TIMESTAMP(0) WITH TIME ZONE NULL,
                created_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT team_members_unique UNIQUE (team_id, user_id)');
        $this->addSql('CREATE INDEX team_members_team_id ON team_members (team_id)');
        $this->addSql('CREATE INDEX team_members_user_id ON team_members (user_id)');
        $this->addSql('CREATE INDEX team_members_pending ON team_members (team_id, accepted_at) WHERE accepted_at IS NULL');
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT fk_team_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT fk_team_members_invited_by FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE api_keys (
                id              UUID         NOT NULL,
                team_id         UUID         NOT NULL,
                created_by      UUID             NULL,
                name            VARCHAR(255) NOT NULL,
                key_hash        TEXT         NOT NULL,
                key_prefix      VARCHAR(12)  NOT NULL,
                scopes          TEXT[]       NOT NULL DEFAULT '{}',
                site_ids        UUID[]       NOT NULL DEFAULT '{}',
                last_used_at    TIMESTAMP(0) WITH TIME ZONE NULL,
                expires_at      TIMESTAMP(0) WITH TIME ZONE NULL,
                revoked_at      TIMESTAMP(0) WITH TIME ZONE NULL,
                created_at      TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX api_keys_hash ON api_keys (key_hash)');
        $this->addSql('CREATE INDEX api_keys_team_id ON api_keys (team_id) WHERE revoked_at IS NULL');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_api_keys_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_api_keys_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
                id            BIGSERIAL    NOT NULL,
                team_id       UUID             NULL,
                actor_id      UUID             NULL,
                actor_email   TEXT             NULL,
                action        VARCHAR(128) NOT NULL,
                resource_type VARCHAR(64)      NULL,
                resource_id   TEXT             NULL,
                payload       JSONB            NULL,
                ip_address    VARCHAR(45)      NULL,
                user_agent    TEXT             NULL,
                created_at    TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX audit_log_team_id ON audit_log (team_id, created_at DESC)');
        $this->addSql('CREATE INDEX audit_log_actor_id ON audit_log (actor_id, created_at DESC)');
        $this->addSql('CREATE INDEX audit_log_action ON audit_log (action, created_at DESC)');
        $this->addSql('CREATE INDEX audit_log_resource ON audit_log (resource_type, resource_id)');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                id          UUID        NOT NULL,
                user_id     UUID        NOT NULL,
                token_hash  VARCHAR(64) NOT NULL,
                expires_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(0) WITH TIME ZONE NULL,
                created_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX password_reset_tokens_hash ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE INDEX password_reset_tokens_user_id ON password_reset_tokens (user_id)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS api_keys');
        $this->addSql('DROP TABLE IF EXISTS team_members');
        $this->addSql('DROP TABLE IF EXISTS teams');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
