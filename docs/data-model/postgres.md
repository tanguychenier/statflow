# PostgreSQL Application Schema — Statflow

> Reference schema: `docs/data-model/postgres-schema.sql`
> ERD: `docs/data-model/erd.md`

---

## 1. Design Principles

PostgreSQL serves as Statflow's **application database** — the source of truth for organisational structure, configuration, and access control. It holds no analytics event data; all measurements live in ClickHouse. The design follows these principles:

1. **Bounded context isolation** — Statflow has the six bounded contexts named in `docs/architecture.md`: Ingestion, Analytics, Identity, Sites, Reporting, Shared. This PostgreSQL schema holds the application state of the **Identity, Sites, and Reporting** contexts (goals and funnels belong to the Sites context). The Ingestion and Analytics contexts are ClickHouse-resident. There is no separate "Configuration", "Goals & Funnels", or "Platform" context — those names from earlier drafts are not used.
2. **Soft deletes by default** — tables with `deleted_at TIMESTAMPTZ NULL` allow row recovery and auditing without foreign-key cascades into ClickHouse.
3. **JSONB for extensible configurations** — segment filter expressions, report definitions, and alert payloads are stored as JSONB. This avoids frequent DDL migrations for configuration schema changes while keeping the data queryable.
4. **No secrets in plaintext** — API key values are hashed (SHA-256) at the application layer before storage. The original plaintext is displayed once and discarded.
5. **Append-only audit log** — the `audit_log` table uses BIGSERIAL (not UUID) and has no UPDATE or DELETE permissions granted to the application role.

---

## 2. Table Inventory

| Table | Rows | Notes |
|---|---|---|
| `users` | Low (thousands) | Authenticated users; soft-deleted |
| `teams` | Low | Billing boundary; one personal team per user |
| `team_members` | Low-medium | Join table with role; supports pending invitations |
| `sites` | Low-medium | One per tracked domain; soft-deleted |
| `site_settings` | 1:1 with sites | Wide settings; separate to keep sites row narrow |
| `api_keys` | Low | Secret programmatic keys (`sfk_*`); team-scoped; SHA-256 hash stored. Not the ingestion credential. |
| `goals` | Low-medium | Conversion goals; pageview or event trigger |
| `funnels` | Low | Ordered step sequences |
| `funnel_steps` | Low | Steps within funnels |
| `segments` | Low-medium | Reusable visitor filter expressions |
| `saved_reports` | Low-medium | Named report configurations |
| `alerts` | Low | Threshold-based metric alerts |
| `audit_log` | High (grows forever) | Append-only; BIGSERIAL PK for partitioning |
| `schema_migrations` | Very low | One row per applied migration |

---

## 3. Identity and Access Layer

### 3.1 Users

`users` stores only the data needed to authenticate and personalise the UI. It does not store event-level PII. Authentication itself is handled by an external auth service (magic link or OAuth 2.0); the `password_hash` column is provisioned for a future optional email+password flow.

The `email` column uses the `CITEXT` extension so that `user@example.com` and `User@Example.com` are treated as the same address at the database level, preventing duplicate account creation from case variations in OAuth tokens.

Soft deletes (`deleted_at`) preserve the user row for foreign-key integrity (e.g., `audit_log.actor_id`) and allow account recovery within a grace period. The unique index on `email` is `WHERE deleted_at IS NULL`, so a deleted email can be re-registered.

### 3.2 Teams and Members

Teams are the billing and permission boundary. Every user has exactly one personal team created at signup (`is_personal = TRUE`). Additional shared teams can be created and other users invited.

`team_members` is a many-to-many join table with roles: `owner`, `admin`, `editor`, `viewer`. The `invited_by` and `accepted_at` columns support the invitation flow: a pending invitation has `accepted_at IS NULL`, which enables separate UI states (pending vs. active) and allows invitation expiry logic to be enforced in the application layer.

The `monthly_event_used` counter on `teams` is incremented by the ingestion service (via Redis counter → batch flush) and reset at the billing cycle. It is advisory — the ingestion service checks this value before accepting events but does not hold a lock, so brief over-quota periods are possible. Hard enforcement happens at the next billing check.

### 3.3 Role Permissions Summary

| Action | owner | admin | editor | viewer |
|---|:---:|:---:|:---:|:---:|
| Delete team | Y | | | |
| Manage billing | Y | | | |
| Invite members | Y | Y | | |
| Create/delete sites | Y | Y | Y | |
| Edit site settings | Y | Y | Y | |
| Create goals/funnels | Y | Y | Y | |
| View analytics | Y | Y | Y | Y |
| Export data | Y | Y | Y | |
| Manage API keys | Y | Y | | |

---

## 4. Sites and Settings

### 4.1 Sites

The `domain` column stores the bare hostname without scheme or trailing slash (e.g., `app.example.com`). The uniqueness constraint is scoped to `(team_id, domain)` — the same domain can be tracked under different teams (multi-tenancy of white-label products). A global cross-team uniqueness constraint is intentionally absent.

`public_stats` and `shared_link_token` support Plausible-style public analytics pages. When `public_stats = TRUE`, a random opaque token is generated and stored in `shared_link_token`; the token appears in the public URL to prevent enumeration. The token can be rotated (generating a new one and invalidating the old URL) without disabling public access.

### 4.2 Site Settings

`site_settings` is a strict 1:1 extension of `sites` (PRIMARY KEY is also the FK). This pattern avoids the overhead of repeatedly joining a wide settings row in queries that only need the core site fields.

The `sampling_rate` column (range 0.000–1.000, default 1.000) enables high-traffic sites to track a fraction of traffic, proportionally scaling down event volume and quota consumption. Sampling is **per session**: the tracker draws a random value once per session and either tracks the whole session or none of it. It cannot be per-visitor because the tracker has no `visitor_id` — that identifier is derived server-side (ADR-0008), so the earlier "`visitor_id mod 1000`" scheme is unimplementable client-side and is not used.

The `excluded_ips` column (`INET[]`) stores IP ranges to exclude from tracking (e.g., office networks). Evaluated at ingestion time before the IP is geo-resolved and discarded.

---

## 5. Goals and Funnels

### 5.1 Goals

A goal represents a single meaningful conversion action. Two trigger types are supported:

- **Pageview goals** (`trigger_type = 'pageview'`) match on URL patterns with `*` wildcard support (e.g., `/checkout/confirmation*`). Matched at event ingestion against the goal list for the site.
- **Event goals** (`trigger_type = 'event'`) match on a custom event name emitted by the site's JavaScript (e.g., `'Signed Up'`). The event name must be in the `site_settings.custom_event_names` allowlist if that field is non-NULL.

The `CHECK` constraint on `goals` ensures mutual exclusivity: a goal cannot simultaneously be a pageview goal and an event goal.

### 5.2 Funnels and Steps

Funnels compose goals into ordered conversion paths. `funnel_steps.step_index` is zero-based and has a `UNIQUE (funnel_id, step_index)` constraint so gaps are not possible without application-layer validation.

Steps can reference an existing `goal_id` (for reuse) or define an inline trigger directly (`url_pattern` or `event_name`). This covers the common case of multi-step funnels where some steps are unique to that funnel and not worth defining as stand-alone goals.

---

## 6. Segments

Segments are stored as JSONB filter arrays, for example:

```json
[
  {"field": "device_type",  "op": "eq",      "value": "mobile"},
  {"field": "country_code", "op": "in",      "value": ["FR", "DE", "ES"]},
  {"field": "utm_source",   "op": "contains","value": "email"}
]
```

The analytics query engine translates this representation into ClickHouse `WHERE` clauses at query time. The GIN index on `segments.filters` allows efficient lookup of "all segments that filter on device_type" — used by the alert evaluation job when finding applicable segments.

`scope = 'private'` segments are only returned in API responses when the requesting user matches `created_by`. `scope = 'shared'` segments are visible to all team members with access to the site.

---

## 7. Alerts

Alerts are evaluated by a background job that runs on the configured `cadence`. The job:

1. Reads all active alerts (`enabled = TRUE, deleted_at IS NULL`) for due evaluation (`last_checked_at + cadence_interval < NOW()`).
2. Constructs and executes a ClickHouse query for each alert's metric + filters.
3. Compares the result against `threshold` using the `direction` operator.
4. On trigger: records `last_triggered_at`, sends notifications, and enforces a cooldown window (configurable, default 1 hour) to prevent alert spam.

`notify_webhook` accepts a URL that receives a JSON POST body with the alert metadata. This enables integration with Slack, PagerDuty, or any HTTP webhook consumer.

---

## 8. Audit Log

The `audit_log` table is append-only by design and convention:

- The application role (`statflow_app`) is granted `INSERT` but not `UPDATE` or `DELETE`.
- `id` uses `BIGSERIAL` rather than UUID to support future range-based partitioning by month.
- `actor_email` is denormalised at write time so the log remains accurate even if the user is later deleted.
- The `payload` JSONB column stores a relevant snapshot: for mutations it contains `{"before": {...}, "after": {...}}`; for deletions it contains `{"snapshot": {...}}`.

Suggested `audit_log` partition strategy (apply after initial release):

```sql
-- Convert to partitioned table by month at sufficient write volume (>10M rows/month)
-- Attach monthly partitions with CHECK constraints on created_at.
-- Old partitions can be archived to cold storage.
```

---

## 9. JSONB Conventions

All JSONB columns follow a schema validated at the application layer (Zod in TypeScript, Pydantic in Python). No `CHECK` constraints on JSONB structure are added at the DB level to avoid migration-churn when the schema evolves. Application validation is the enforcement point.

JSONB GIN indexes are added where the field is used in queries (`segments.filters`, `alerts.filters`). For `saved_reports.definition` and `audit_log.payload`, which are read but not filtered, no GIN index is added.

---

## 10. Indexing Strategy

| Pattern | Index type | Tables |
|---|---|---|
| PK lookups | B-tree on UUID PK | All tables |
| Foreign key traversal | B-tree on FK columns | team_members, sites, goals, funnels, etc. |
| Soft-delete scans | Partial B-tree `WHERE deleted_at IS NULL` | users, teams, sites, goals, funnels, segments, saved_reports, alerts |
| Fuzzy name search | GIN trigram (`pg_trgm`) | sites.name |
| JSONB filter queries | GIN | segments.filters |
| Audit log range scans | B-tree on (team_id, created_at DESC) | audit_log |
| Pending invitations | Partial B-tree on (team_id) WHERE accepted_at IS NULL | team_members |

---

## 11. Migrations

`schema_migrations` tracks applied migrations by version string (ISO timestamp prefix + slug, e.g., `20240601_001_create_users`). The `checksum` column stores a SHA-256 of the migration file to detect post-apply modifications. The migration runner refuses to proceed if a checksum mismatch is found.

All migrations must be:

- **Idempotent** — use `CREATE TABLE IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`, etc.
- **Non-blocking** — use `CREATE INDEX CONCURRENTLY` for adding indexes on large tables. Never take explicit locks in migrations.
- **Reversible** — each migration file has a paired `down` script.

---

## 12. Open Questions

1. **Team-scoped vs. site-scoped API keys** — **Resolved (ADR-0009).** Programmatic
   `api_keys` are **team-scoped** and may be narrowed to a subset of the team's sites
   via the `site_ids UUID[]` column (empty array = all sites in the team). Scopes use
   the granular `resource:action` vocabulary stored in `scopes TEXT[]`. The earlier
   single-`site_id`-FK design with a coarse `api_key_scope` enum is withdrawn. The
   ingestion credential is separate — it is the public `stk_` key on `sites.tracker_key`.

2. **`audit_log` partitioning threshold** — converting `audit_log` to a partitioned table is disruptive. The threshold should be defined before launch (suggested: partition when projected annual volume exceeds 50M rows or table size exceeds 10 GB).

3. **Funnel step ordering gaps** — the `UNIQUE (funnel_id, step_index)` constraint prevents gaps, but when a step is deleted the application must re-index subsequent steps. This requires a transaction that updates multiple rows. Consider whether a linked-list (`next_step_id`) approach would be simpler, at the cost of more complex ordering queries.

4. **Segment versioning** — when a segment's filters are updated, historical reports that used that segment will silently change. Consider an immutable snapshot model (create a new segment version on each edit) for reproducible reporting.

5. **`schema_migrations` vs. dedicated migration tool** — this table is compatible with Flyway, Liquibase, and golang-migrate. The team should pick one and remove the custom runner before the first production deployment.
