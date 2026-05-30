# Entity-Relationship Diagram — PostgreSQL Schema

> This diagram covers the `statflow` PostgreSQL schema only.
> ClickHouse tables are not shown (they contain no relational FK structure).
> Rendered with Mermaid `erDiagram` syntax.

---

```mermaid
erDiagram

    %% -----------------------------------------------------------------------
    %% IDENTITY BOUNDED CONTEXT
    %% -----------------------------------------------------------------------

    users {
        UUID        id              PK
        CITEXT      email           UK
        VARCHAR     name
        TEXT        avatar_url
        TEXT        password_hash
        BOOLEAN     email_verified
        TIMESTAMPTZ last_login_at
        VARCHAR     timezone
        VARCHAR     locale
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    teams {
        UUID        id              PK
        VARCHAR     name
        VARCHAR     slug            UK
        UUID        owner_id        FK
        BOOLEAN     is_personal
        VARCHAR     plan
        TIMESTAMPTZ plan_expires_at
        TEXT        stripe_customer_id
        BIGINT      monthly_event_quota
        BIGINT      monthly_event_used
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    team_members {
        UUID        id              PK
        UUID        team_id         FK
        UUID        user_id         FK
        team_role   role
        UUID        invited_by      FK
        TIMESTAMPTZ invited_at
        TIMESTAMPTZ accepted_at
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
    }

    %% -----------------------------------------------------------------------
    %% SITES BOUNDED CONTEXT
    %% -----------------------------------------------------------------------

    sites {
        UUID        id              PK
        UUID        team_id         FK
        VARCHAR     name
        VARCHAR     domain
        VARCHAR     timezone
        BOOLEAN     tracking_enabled
        BOOLEAN     public_stats
        TEXT        shared_link_token
        SMALLINT    retention_months
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    site_settings {
        UUID        site_id         PK-FK
        BOOLEAN     hash_based_routing
        BOOLEAN     outbound_link_tracking
        BOOLEAN     file_download_tracking
        BOOLEAN     form_submission_tracking
        TEXT_ARRAY  custom_event_names
        BOOLEAN     heatmaps_enabled
        BOOLEAN     scroll_tracking_enabled
        BOOLEAN     rage_click_tracking
        BOOLEAN     session_recording
        NUMERIC     sampling_rate
        INET_ARRAY  excluded_ips
        CHAR2_ARRAY excluded_countries
        VARCHAR     script_variant
        BOOLEAN     csp_nonce_required
        TIMESTAMPTZ updated_at
    }

    api_keys {
        UUID            id          PK
        UUID            site_id     FK
        UUID            created_by  FK
        VARCHAR         name
        TEXT            key_hash    UK
        CHAR8           key_prefix
        api_key_scope   scope
        TIMESTAMPTZ     last_used_at
        TIMESTAMPTZ     expires_at
        TIMESTAMPTZ     revoked_at
        TIMESTAMPTZ     created_at
    }

    %% -----------------------------------------------------------------------
    %% GOALS AND FUNNELS BOUNDED CONTEXT
    %% -----------------------------------------------------------------------

    goals {
        UUID                id          PK
        UUID                site_id     FK
        VARCHAR             name
        goal_trigger_type   trigger_type
        TEXT                url_pattern
        VARCHAR             event_name
        CHAR3               currency
        NUMERIC             revenue_value
        TIMESTAMPTZ         created_at
        TIMESTAMPTZ         updated_at
        TIMESTAMPTZ         deleted_at
    }

    funnels {
        UUID        id          PK
        UUID        site_id     FK
        VARCHAR     name
        UUID        created_by  FK
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    funnel_steps {
        UUID                id              PK
        UUID                funnel_id       FK
        UUID                goal_id         FK
        SMALLINT            step_index      UK
        VARCHAR             name
        goal_trigger_type   trigger_type
        TEXT                url_pattern
        VARCHAR             event_name
    }

    %% -----------------------------------------------------------------------
    %% REPORTING BOUNDED CONTEXT
    %% -----------------------------------------------------------------------

    segments {
        UUID        id          PK
        UUID        site_id     FK
        VARCHAR     name
        CHAR7       color
        JSONB       filters
        VARCHAR     scope
        UUID        created_by  FK
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    saved_reports {
        UUID        id              PK
        UUID        site_id         FK
        VARCHAR     name
        TEXT        description
        UUID        created_by      FK
        JSONB       definition
        BOOLEAN     pinned
        SMALLINT    pin_order
        VARCHAR     schedule_cron
        TEXT_ARRAY  schedule_emails
        TIMESTAMPTZ created_at
        TIMESTAMPTZ updated_at
        TIMESTAMPTZ deleted_at
    }

    alerts {
        UUID            id                  PK
        UUID            site_id             FK
        VARCHAR         name
        UUID            created_by          FK
        alert_metric    metric
        alert_direction direction
        NUMERIC         threshold
        alert_cadence   cadence
        JSONB           filters
        TEXT_ARRAY      notify_emails
        TEXT            notify_webhook
        BOOLEAN         enabled
        TIMESTAMPTZ     last_triggered_at
        TIMESTAMPTZ     last_checked_at
        TIMESTAMPTZ     created_at
        TIMESTAMPTZ     updated_at
        TIMESTAMPTZ     deleted_at
    }

    %% -----------------------------------------------------------------------
    %% AUDIT AND MIGRATIONS
    %% -----------------------------------------------------------------------

    audit_log {
        BIGSERIAL   id              PK
        UUID        team_id         FK
        UUID        actor_id        FK
        TEXT        actor_email
        VARCHAR     action
        VARCHAR     resource_type
        TEXT        resource_id
        JSONB       payload
        INET        ip_address
        TEXT        user_agent
        TIMESTAMPTZ created_at
    }

    schema_migrations {
        VARCHAR     version     PK
        TIMESTAMPTZ applied_at
        CHAR64      checksum
        INTEGER     execution_ms
        TEXT        applied_by
    }

    %% -----------------------------------------------------------------------
    %% RELATIONSHIPS
    %% -----------------------------------------------------------------------

    %% Identity
    users       ||--o{  teams           : "owns (owner_id)"
    users       ||--o{  team_members    : "is member"
    teams       ||--o{  team_members    : "has members"
    users       ||--o{  team_members    : "invited_by"

    %% Sites
    teams       ||--o{  sites           : "owns"
    sites       ||--||  site_settings   : "has settings"
    sites       ||--o{  api_keys        : "has keys"
    users       ||--o{  api_keys        : "created_by"

    %% Goals and funnels
    sites       ||--o{  goals           : "defines"
    sites       ||--o{  funnels         : "defines"
    users       ||--o{  funnels         : "created_by"
    funnels     ||--o{  funnel_steps    : "contains"
    goals       ||--o{  funnel_steps    : "referenced by"

    %% Reporting
    sites       ||--o{  segments        : "defines"
    users       ||--o{  segments        : "created_by"
    sites       ||--o{  saved_reports   : "has"
    users       ||--o{  saved_reports   : "created_by"
    sites       ||--o{  alerts          : "has"
    users       ||--o{  alerts          : "created_by"

    %% Audit
    teams       ||--o{  audit_log       : "team activity"
    users       ||--o{  audit_log       : "actor activity"
```

---

## Notation Key

| Symbol | Meaning |
|---|---|
| `\|\|--\|\|` | Exactly one to exactly one (mandatory both sides) |
| `\|\|--o{` | Exactly one to zero-or-many |
| `o\|--o{` | Zero-or-one to zero-or-many |

---

## Bounded Context Map

Statflow has the six bounded contexts named in `docs/architecture.md`:
**Ingestion, Analytics, Identity, Sites, Reporting, Shared**. The PostgreSQL
tables belong to the Identity, Sites, and Reporting contexts — there is no
separate "Goals & Funnels", "Platform", or "Configuration" context. Goals and
funnels are part of the **Sites** context; `audit_log` and `schema_migrations`
are infrastructure of the **Shared** context.

```
┌─────────────────────────────────────────────────────────────────┐
│  IDENTITY                         │  SITES                       │
│  users                            │  sites                       │
│  teams                            │  site_settings               │
│  team_members                     │  api_keys                    │
│                                   │  goals                       │
│                                   │  funnels                     │
│                                   │  funnel_steps                │
├───────────────────────────────────┼──────────────────────────────┤
│  REPORTING                        │  SHARED (cross-cutting)      │
│  segments                         │  audit_log                   │
│  saved_reports                    │  schema_migrations           │
│  alerts                           │                              │
└───────────────────────────────────┴──────────────────────────────┘
```

**ClickHouse tables** (events, sessions, daily_stats, page_stats, source_stats, heatmap_stats, scroll_depth_stats, funnel_events, retention_cohorts) belong to the **Ingestion** and **Analytics** bounded contexts. They reference `site_id` and `funnel_id` as advisory foreign keys (UUID values that match PostgreSQL PKs) but no FK enforcement exists across the ClickHouse/PostgreSQL boundary.
