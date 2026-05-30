-- =============================================================================
-- Statflow — PostgreSQL Application Schema
-- Version: 1.0.0
-- Engine: PostgreSQL 16+
-- Conventions:
--   - All primary keys: UUID (gen_random_uuid())
--   - All timestamps: TIMESTAMPTZ (UTC stored, displayed per user timezone)
--   - Soft deletes: deleted_at TIMESTAMPTZ NULL (NULL = active)
--   - Audit trail: audit_log table for all sensitive mutations
--   - Text search: pg_trgm extension for site/report name search
-- =============================================================================

-- ---------------------------------------------------------------------------
-- EXTENSIONS
-- ---------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";    -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "pg_trgm";     -- trigram text search
CREATE EXTENSION IF NOT EXISTS "citext";      -- case-insensitive email

-- ---------------------------------------------------------------------------
-- SCHEMA
-- ---------------------------------------------------------------------------
CREATE SCHEMA IF NOT EXISTS statflow;
SET search_path TO statflow, public;

-- ---------------------------------------------------------------------------
-- 1. USERS
-- ---------------------------------------------------------------------------
-- Represents authenticated human users of the Statflow web application.
-- v1 ships email + password authentication (session-based dashboard auth);
-- password_hash holds a bcrypt hash and is populated for every active user.
-- OAuth / SSO is deferred post-v1 (see ADR-0009); password_hash may then be
-- NULL for accounts created through an external identity provider.
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id                  UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    email               CITEXT          NOT NULL,
    name                VARCHAR(255)    NOT NULL,
    avatar_url          TEXT,
    password_hash       TEXT,                       -- bcrypt; populated in v1
    email_verified      BOOLEAN         NOT NULL DEFAULT FALSE,
    last_login_at       TIMESTAMPTZ,
    timezone            VARCHAR(64)     NOT NULL DEFAULT 'UTC',
    locale              VARCHAR(10)     NOT NULL DEFAULT 'en',
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,                            -- soft delete

    CONSTRAINT users_email_format CHECK (email ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$')
);

CREATE UNIQUE INDEX users_email_unique
    ON users (email)
    WHERE deleted_at IS NULL;

CREATE INDEX users_deleted_at ON users (deleted_at)
    WHERE deleted_at IS NOT NULL;

COMMENT ON TABLE users IS 'Authenticated human users of the Statflow application.';
COMMENT ON COLUMN users.password_hash IS 'bcrypt hash. Populated for v1 email+password accounts; may be NULL for post-v1 OAuth/SSO accounts.';

-- ---------------------------------------------------------------------------
-- 2. TEAMS
-- ---------------------------------------------------------------------------
-- A team is the billing and access-control boundary.
-- Each user has exactly one personal team (is_personal = TRUE) created on
-- sign-up, plus any number of shared teams they have been invited to.
-- ---------------------------------------------------------------------------
CREATE TABLE teams (
    id                  UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    name                VARCHAR(255)    NOT NULL,
    slug                VARCHAR(63)     NOT NULL,   -- URL-safe, globally unique
    owner_id            UUID            NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    is_personal         BOOLEAN         NOT NULL DEFAULT FALSE,

    -- Billing / plan
    plan                VARCHAR(32)     NOT NULL DEFAULT 'free',   -- 'free','starter','pro','enterprise'
    plan_expires_at     TIMESTAMPTZ,
    stripe_customer_id  TEXT,                       -- NULL for free plans
    monthly_event_quota BIGINT          NOT NULL DEFAULT 100000,
    monthly_event_used  BIGINT          NOT NULL DEFAULT 0,        -- reset on billing cycle

    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,

    CONSTRAINT teams_slug_format CHECK (slug ~ '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$')
);

CREATE UNIQUE INDEX teams_slug_unique
    ON teams (slug)
    WHERE deleted_at IS NULL;

CREATE INDEX teams_owner_id ON teams (owner_id);

COMMENT ON TABLE teams IS 'Billing and access-control boundary. One personal team per user, unlimited shared teams.';
COMMENT ON COLUMN teams.slug IS 'URL-safe team identifier used in API paths. Globally unique among active teams.';

-- ---------------------------------------------------------------------------
-- 3. TEAM MEMBERS
-- ---------------------------------------------------------------------------
CREATE TYPE team_role AS ENUM ('owner', 'admin', 'editor', 'viewer');

CREATE TABLE team_members (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id     UUID        NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    user_id     UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role        team_role   NOT NULL DEFAULT 'viewer',
    invited_by  UUID        REFERENCES users(id) ON DELETE SET NULL,
    invited_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    accepted_at TIMESTAMPTZ,                        -- NULL = pending invitation
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT team_members_unique UNIQUE (team_id, user_id)
);

CREATE INDEX team_members_team_id ON team_members (team_id);
CREATE INDEX team_members_user_id ON team_members (user_id);
CREATE INDEX team_members_pending  ON team_members (team_id, accepted_at)
    WHERE accepted_at IS NULL;

COMMENT ON TABLE team_members IS 'Join table linking users to teams with role-based access control.';
COMMENT ON COLUMN team_members.accepted_at IS 'NULL while the invitation is pending; set on first login after invite.';

-- ---------------------------------------------------------------------------
-- 4. SITES
-- ---------------------------------------------------------------------------
-- A site represents one tracked web property (domain + optional sub-path).
-- Multiple sites can belong to the same team.
-- ---------------------------------------------------------------------------
CREATE TABLE sites (
    id              UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id         UUID            NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    name            VARCHAR(255)    NOT NULL,
    domain          VARCHAR(253)    NOT NULL,   -- bare hostname, no scheme, no trailing slash
    timezone        VARCHAR(64)     NOT NULL DEFAULT 'UTC',

    -- Tracking configuration
    tracking_enabled    BOOLEAN     NOT NULL DEFAULT TRUE,
    public_stats        BOOLEAN     NOT NULL DEFAULT FALSE,  -- shared dashboard URL
    shared_link_token   TEXT,                                -- set when public_stats = TRUE
    shared_link_password_hash TEXT,                          -- bcrypt; NULL = no password on the share link

    -- Public ingestion identifier embedded in the JS tracker snippet (ADR-0009).
    -- Public by design; carries no privilege beyond submitting events for this site.
    tracker_key         TEXT        NOT NULL,                -- 'stk_' prefixed

    -- Data retention override (NULL = inherit team/global default of 365 days).
    -- Unit: days. Range 30-730 — aligned with SiteSettings.data_retention_days
    -- in the API and with the ClickHouse per-partition TTL mechanism.
    retention_days      INTEGER     CHECK (retention_days BETWEEN 30 AND 730),

    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ,

    CONSTRAINT sites_domain_format
        CHECK (domain ~ '^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$'
            OR domain = 'localhost')
);

CREATE UNIQUE INDEX sites_domain_team_unique
    ON sites (team_id, domain)
    WHERE deleted_at IS NULL;

CREATE UNIQUE INDEX sites_tracker_key_unique ON sites (tracker_key);
CREATE INDEX sites_team_id ON sites (team_id);
CREATE INDEX sites_domain  ON sites (domain);
-- GIN index for fuzzy name search
CREATE INDEX sites_name_trgm ON sites USING GIN (name gin_trgm_ops);

COMMENT ON TABLE sites IS 'A tracked web property. Domain must be unique per team.';
COMMENT ON COLUMN sites.shared_link_token IS 'Opaque token appended to the public stats URL. NULL when public_stats is FALSE.';
COMMENT ON COLUMN sites.shared_link_password_hash IS 'bcrypt hash of the optional password protecting the public share link. NULL = link is unprotected.';
COMMENT ON COLUMN sites.tracker_key IS 'Public ''stk_'' ingestion key embedded in the JS snippet. One per site; regenerating issues a new value (ADR-0009).';
COMMENT ON COLUMN sites.retention_days IS 'Per-site raw-event retention override in days (30-730). NULL = inherit the global 365-day default.';

-- ---------------------------------------------------------------------------
-- 5. SITE SETTINGS
-- ---------------------------------------------------------------------------
-- One-to-one extension of sites for settings that are rarely read.
-- Stored separately to keep the sites row narrow.
-- ---------------------------------------------------------------------------
CREATE TABLE site_settings (
    site_id             UUID        PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE,

    -- Tracker behaviour
    hash_based_routing      BOOLEAN NOT NULL DEFAULT FALSE,   -- track #hash changes as pageviews
    outbound_link_tracking  BOOLEAN NOT NULL DEFAULT TRUE,
    file_download_tracking  BOOLEAN NOT NULL DEFAULT TRUE,
    form_submission_tracking BOOLEAN NOT NULL DEFAULT FALSE,
    custom_event_names      TEXT[],   -- allowlist of custom event names; NULL = allow all

    -- Heatmap and behavioural tracking
    heatmaps_enabled        BOOLEAN NOT NULL DEFAULT FALSE,
    scroll_tracking_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    rage_click_tracking     BOOLEAN NOT NULL DEFAULT TRUE,
    session_recording       BOOLEAN NOT NULL DEFAULT FALSE,   -- future feature flag

    -- Domain allowlist: origins permitted to submit events for this site.
    -- Empty array = allow all origins (not recommended for production).
    -- Validated against the request Origin header by the ingestion endpoint.
    allowed_domains         TEXT[]  NOT NULL DEFAULT '{}',

    -- Bot / spider filtering toggle (roadmap 1.4).
    bot_filtering_enabled   BOOLEAN NOT NULL DEFAULT TRUE,

    -- Per-session client-side sampling (0.0–1.0; 1.0 = track every session).
    -- 0.0 is permitted and means "track nothing". Range matches the API.
    sampling_rate           NUMERIC(4,3) NOT NULL DEFAULT 1.0
        CHECK (sampling_rate BETWEEN 0.000 AND 1.000),

    -- IP / geo exclusions
    excluded_ips            INET[],
    excluded_countries      CHAR(2)[],

    -- JavaScript snippet configuration
    -- 'default'  standard snippet
    -- 'compat'   wider browser compatibility build
    -- 'manual'   manual page() calls only (no auto pageview)
    -- 'entropy'  adds the short-lived browser-entropy CGNAT mitigation (identity-and-privacy.md §5.2)
    script_variant          VARCHAR(32) NOT NULL DEFAULT 'default'
        CHECK (script_variant IN ('default', 'compat', 'manual', 'entropy')),
    csp_nonce_required      BOOLEAN     NOT NULL DEFAULT FALSE,

    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE site_settings IS 'Extended configuration for a site; one-to-one with sites.';
COMMENT ON COLUMN site_settings.sampling_rate IS 'Fraction of visitors for which events are actually tracked. Useful for very high-traffic sites.';

-- ---------------------------------------------------------------------------
-- 6. API KEYS
-- ---------------------------------------------------------------------------
-- Programmatic API keys for the Stats / Reporting / Sites APIs (ADR-0009).
-- These are SECRET keys, prefixed 'sfk_live_' / 'sfk_test_', shown once on
-- creation and stored only as a SHA-256 hash.
--
-- These are NOT the ingestion credential: event ingestion is authenticated by
-- the public 'stk_' tracker key on sites.tracker_key, not by an api_keys row.
--
-- A key is team-scoped and may be restricted to a subset of the team's sites
-- via site_ids (empty array = all sites in the team). Scopes use the granular
-- resource:action vocabulary: analytics:read, sites:read, sites:write,
-- reports:read, reports:write.
-- ---------------------------------------------------------------------------
CREATE TABLE api_keys (
    id              UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id         UUID            NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    created_by      UUID            REFERENCES users(id) ON DELETE SET NULL,
    name            VARCHAR(255)    NOT NULL,
    key_hash        TEXT            NOT NULL,        -- SHA-256 hex of the plaintext key
    key_prefix      CHAR(12)        NOT NULL,        -- first 12 chars shown in UI for identification
    scopes          TEXT[]          NOT NULL DEFAULT '{}',  -- e.g. {analytics:read,reports:read}
    site_ids        UUID[]          NOT NULL DEFAULT '{}',  -- empty = all sites in the team
    last_used_at    TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    revoked_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    CONSTRAINT api_keys_scopes_valid CHECK (
        scopes <@ ARRAY[
            'analytics:read', 'sites:read', 'sites:write',
            'reports:read', 'reports:write'
        ]::TEXT[]
    )
);

CREATE UNIQUE INDEX api_keys_hash    ON api_keys (key_hash);
CREATE INDEX api_keys_team_id        ON api_keys (team_id)
    WHERE revoked_at IS NULL;

COMMENT ON TABLE api_keys IS 'Secret programmatic API keys (sfk_*) for the Stats/Reporting/Sites APIs. Team-scoped. Plaintext shown once; only SHA-256 hash stored. Not the ingestion credential.';
COMMENT ON COLUMN api_keys.scopes IS 'Granted resource:action scopes. Subset of the fixed five-scope vocabulary.';
COMMENT ON COLUMN api_keys.site_ids IS 'Sites this key may access. Empty array = every site in the team.';

-- ---------------------------------------------------------------------------
-- 7. GOALS
-- ---------------------------------------------------------------------------
-- A goal defines a conversion event to track (e.g., "Signed up", "Purchase").
-- Goals can be triggered by a URL pattern match or a custom event name.
-- ---------------------------------------------------------------------------
CREATE TYPE goal_trigger_type AS ENUM ('pageview', 'event');

CREATE TABLE goals (
    id              UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    site_id         UUID            NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name            VARCHAR(255)    NOT NULL,
    trigger_type    goal_trigger_type NOT NULL,

    -- For trigger_type = 'pageview': URL pattern (supports * wildcard)
    url_pattern     TEXT,
    -- For trigger_type = 'event': exact event name to match
    event_name      VARCHAR(255),

    -- Optional revenue tracking
    currency        CHAR(3),
    -- Static value (NULL if tracked dynamically via event property)
    revenue_value   NUMERIC(18, 4),

    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ,

    CONSTRAINT goals_trigger_check CHECK (
        (trigger_type = 'pageview' AND url_pattern IS NOT NULL AND event_name IS NULL)
        OR
        (trigger_type = 'event' AND event_name IS NOT NULL AND url_pattern IS NULL)
    )
);

CREATE INDEX goals_site_id ON goals (site_id)
    WHERE deleted_at IS NULL;

COMMENT ON TABLE goals IS 'Conversion goals. Triggered by URL pattern (pageview) or custom event name.';

-- ---------------------------------------------------------------------------
-- 8. FUNNELS
-- ---------------------------------------------------------------------------
-- A funnel is an ordered sequence of goals (steps). Each step references a
-- goal_id (for event steps) or specifies a URL pattern directly.
-- ---------------------------------------------------------------------------
CREATE TABLE funnels (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    site_id     UUID        NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name        VARCHAR(255) NOT NULL,
    created_by  UUID        REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ
);

CREATE INDEX funnels_site_id ON funnels (site_id)
    WHERE deleted_at IS NULL;

CREATE TABLE funnel_steps (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    funnel_id       UUID        NOT NULL REFERENCES funnels(id) ON DELETE CASCADE,
    goal_id         UUID        REFERENCES goals(id) ON DELETE SET NULL,
    step_index      SMALLINT    NOT NULL CHECK (step_index >= 0),
    name            VARCHAR(255) NOT NULL,

    -- Override or supplement goal_id with an inline pattern
    trigger_type    goal_trigger_type NOT NULL,
    url_pattern     TEXT,
    event_name      VARCHAR(255),

    CONSTRAINT funnel_steps_unique UNIQUE (funnel_id, step_index),
    CONSTRAINT funnel_steps_trigger_check CHECK (
        goal_id IS NOT NULL
        OR url_pattern IS NOT NULL
        OR event_name IS NOT NULL
    )
);

CREATE INDEX funnel_steps_funnel_id ON funnel_steps (funnel_id);
CREATE INDEX funnel_steps_goal_id   ON funnel_steps (goal_id);

COMMENT ON TABLE funnels IS 'Ordered sequences of steps for conversion funnel analysis.';
COMMENT ON TABLE funnel_steps IS 'Individual steps within a funnel. Steps can reference a goal or define an inline trigger.';

-- ---------------------------------------------------------------------------
-- 9. SEGMENTS
-- ---------------------------------------------------------------------------
-- A segment is a reusable visitor/session filter (e.g., "Mobile users from France").
-- Stored as a JSONB filter expression evaluated by the analytics query engine.
-- ---------------------------------------------------------------------------
CREATE TABLE segments (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    site_id     UUID        NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name        VARCHAR(255) NOT NULL,
    color       CHAR(7)     NOT NULL DEFAULT '#6366F1',  -- hex color for UI
    filters     JSONB       NOT NULL DEFAULT '[]',

    -- Scope: 'private' = only visible to created_by user; 'shared' = all site members
    scope       VARCHAR(16) NOT NULL DEFAULT 'private'
        CHECK (scope IN ('private', 'shared')),
    created_by  UUID        REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ
);

CREATE INDEX segments_site_id ON segments (site_id)
    WHERE deleted_at IS NULL;
CREATE INDEX segments_filters  ON segments USING GIN (filters);

COMMENT ON TABLE segments IS 'Reusable visitor/session filter expressions stored as JSONB.';
COMMENT ON COLUMN segments.filters IS
    'Array of filter objects: [{"field":"device_type","op":"eq","value":"mobile"}, ...]. Evaluated by the analytics query engine.';

-- ---------------------------------------------------------------------------
-- 10. SAVED REPORTS
-- ---------------------------------------------------------------------------
-- Named, shareable report configurations (date range, metrics, breakdowns, segments).
-- ---------------------------------------------------------------------------
CREATE TABLE saved_reports (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    site_id         UUID        NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    created_by      UUID        REFERENCES users(id) ON DELETE SET NULL,

    -- Report definition stored as JSONB (date range presets, selected metrics, etc.)
    definition      JSONB       NOT NULL DEFAULT '{}',

    -- Pinned to dashboard
    pinned          BOOLEAN     NOT NULL DEFAULT FALSE,
    pin_order       SMALLINT,

    -- Scheduled email delivery
    schedule_cron   VARCHAR(100),           -- NULL = no schedule
    schedule_emails TEXT[],                 -- recipient email addresses

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);

CREATE INDEX saved_reports_site_id ON saved_reports (site_id)
    WHERE deleted_at IS NULL;
CREATE INDEX saved_reports_pinned  ON saved_reports (site_id, pin_order)
    WHERE pinned = TRUE AND deleted_at IS NULL;

COMMENT ON TABLE saved_reports IS 'Saved and optionally scheduled report configurations.';

-- ---------------------------------------------------------------------------
-- 11. ALERTS
-- ---------------------------------------------------------------------------
-- Threshold-based alerts that fire when a metric crosses a boundary.
-- Evaluated by a background job that queries ClickHouse on the configured cadence.
-- ---------------------------------------------------------------------------
CREATE TYPE alert_metric AS ENUM (
    'pageviews', 'unique_visitors', 'sessions', 'bounce_rate',
    'conversion_rate', 'revenue', 'error_rate'
);

CREATE TYPE alert_direction AS ENUM ('above', 'below');
CREATE TYPE alert_cadence   AS ENUM ('realtime', 'hourly', 'daily', 'weekly');

CREATE TABLE alerts (
    id              UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    site_id         UUID            NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name            VARCHAR(255)    NOT NULL,
    created_by      UUID            REFERENCES users(id) ON DELETE SET NULL,

    metric          alert_metric    NOT NULL,
    direction       alert_direction NOT NULL,
    threshold       NUMERIC(18, 4)  NOT NULL,
    cadence         alert_cadence   NOT NULL DEFAULT 'daily',

    -- Optional filters (reuse segment JSONB format)
    filters         JSONB           NOT NULL DEFAULT '[]',

    -- Notification channels
    notify_emails   TEXT[]          NOT NULL DEFAULT '{}',
    notify_webhook  TEXT,           -- URL to POST to on trigger

    enabled         BOOLEAN         NOT NULL DEFAULT TRUE,
    last_triggered_at TIMESTAMPTZ,
    last_checked_at   TIMESTAMPTZ,

    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);

CREATE INDEX alerts_site_id ON alerts (site_id)
    WHERE deleted_at IS NULL AND enabled = TRUE;

COMMENT ON TABLE alerts IS 'Threshold-based metric alerts evaluated on a configurable cadence.';

-- ---------------------------------------------------------------------------
-- 12. AUDIT LOG
-- ---------------------------------------------------------------------------
-- Append-only log of sensitive mutations (user management, billing, site deletion,
-- API key operations). Rows are never updated or deleted.
-- actor_id can be NULL for system-initiated actions.
-- ---------------------------------------------------------------------------
CREATE TABLE audit_log (
    id          BIGSERIAL   PRIMARY KEY,
    team_id     UUID        REFERENCES teams(id) ON DELETE SET NULL,
    actor_id    UUID        REFERENCES users(id) ON DELETE SET NULL,
    actor_email TEXT,                   -- denormalised in case actor is later deleted
    action      VARCHAR(128) NOT NULL,  -- e.g. 'site.created', 'api_key.revoked'
    resource_type VARCHAR(64),          -- e.g. 'site', 'api_key', 'team_member'
    resource_id TEXT,                   -- UUID or other identifier of the affected resource
    payload     JSONB,                  -- before/after snapshot or relevant metadata
    ip_address  INET,
    user_agent  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Partial index for team-scoped audit queries (the common access pattern)
CREATE INDEX audit_log_team_id    ON audit_log (team_id, created_at DESC);
CREATE INDEX audit_log_actor_id   ON audit_log (actor_id, created_at DESC);
CREATE INDEX audit_log_action     ON audit_log (action, created_at DESC);
CREATE INDEX audit_log_resource   ON audit_log (resource_type, resource_id);

COMMENT ON TABLE audit_log IS 'Append-only audit trail. Never update or delete rows.';
COMMENT ON COLUMN audit_log.actor_email IS 'Denormalised email snapshot at the time of the action, preserved if user is later deleted.';

-- ---------------------------------------------------------------------------
-- 13. SCHEMA MIGRATIONS
-- ---------------------------------------------------------------------------
-- Simple migration tracking table (compatible with standard migration tools).
-- ---------------------------------------------------------------------------
CREATE TABLE schema_migrations (
    version         VARCHAR(128)    PRIMARY KEY,
    applied_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    checksum        CHAR(64),       -- SHA-256 of the migration file contents
    execution_ms    INTEGER,
    applied_by      TEXT            NOT NULL DEFAULT current_user
);

COMMENT ON TABLE schema_migrations IS 'Migration version tracking. One row per applied migration script.';

-- ---------------------------------------------------------------------------
-- 14. UPDATED_AT TRIGGER FUNCTION
-- ---------------------------------------------------------------------------
-- Automatically maintain updated_at on all tables that have it.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION statflow.set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

DO $$
DECLARE
    t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'users', 'teams', 'team_members', 'sites', 'site_settings',
        'goals', 'funnels', 'funnel_steps', 'segments', 'saved_reports', 'alerts'
    ]
    LOOP
        EXECUTE format(
            'CREATE TRIGGER trg_%1$s_updated_at
                BEFORE UPDATE ON statflow.%1$s
                FOR EACH ROW EXECUTE FUNCTION statflow.set_updated_at()',
            t
        );
    END LOOP;
END;
$$;

-- ---------------------------------------------------------------------------
-- 15. ROW-LEVEL SECURITY (scaffolding — enable per environment)
-- ---------------------------------------------------------------------------
-- RLS is declared here but NOT enabled by default. Enable in production after
-- setting the application role and confirming policy correctness.
--
-- Example for sites table:
--
-- ALTER TABLE statflow.sites ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY sites_team_isolation ON statflow.sites
--     USING (
--         team_id IN (
--             SELECT team_id FROM statflow.team_members
--             WHERE user_id = current_setting('app.current_user_id')::UUID
--         )
--     );
