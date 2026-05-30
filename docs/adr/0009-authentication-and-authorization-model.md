# 0009 — Authentication and Authorization Model

Date: 2026-05-16

## Status

Accepted

## Context

Authentication was specified inconsistently across four surfaces:

- **Ingestion credential.** Four documents described four mechanisms: a public
  `stk_` site key in the body or `Authorization` header (`api/README.md`,
  `openapi.yaml`); a defined-but-unused `TrackerKeyAuth` scheme with
  `security: []` on the actual operation; a *secret* `X-Statflow-Token` header
  injected by a proxy (`anti-adblock.md`); and a `pid` (`proj_…`) field with no
  key at all (`event-taxonomy.md`). The PostgreSQL schema had no storage for
  any of them.
- **Dashboard auth.** OpenAPI and the README ship full email+password login.
  The PostgreSQL `users` comment says password auth is "always NULL in the
  current release" and auth is delegated to magic-link / OAuth. Screen 7 shows
  a password field, GitHub/Google OAuth buttons, and a forgot-password flow,
  but OpenAPI has no OAuth endpoints.
- **Team roles.** OpenAPI, the README, and the design system specify 3 roles
  (owner/admin/viewer); the PostgreSQL enum, `postgres.md`, and the roadmap
  specify 4 (owner/admin/**editor**/viewer).
- **API-key scopes.** OpenAPI uses granular `resource:action` scopes; the
  PostgreSQL enum is a coarse `ingest/read/admin`. Hash algorithm, prefix
  length, and single-site-vs-multi-site key shape all disagreed.

## Decision

### 1. Ingestion credential — public per-site key, in the body

- A site is identified by a single public **site key**, prefix `stk_`,
  stored as the `tracker_key` column on the `sites` table. There is exactly
  one such key per site; regenerating it issues a new value.
- It is a **public identifier, not a secret**. It is embedded in client-side
  JavaScript by design and grants nothing beyond "submit events for this site."
- It is carried as the `site_key` field **in the request body** — never a
  header — so that `navigator.sendBeacon` works without a CORS preflight.
- It is validated together with two non-credential controls: a per-site
  **domain allowlist** (`site_settings.allowed_domains`, checked against the
  request `Origin`) and **per-site rate limiting**.
- The secret `X-Statflow-Token` design from `anti-adblock.md` is **removed**.
  The first-party proxy still works — it transparently forwards
  `POST /api/v1/events` — but it forwards the public `site_key` already present
  in the body and injects no secret. The proxy's value is ad-blocker evasion
  and first-party `X-Forwarded-For`, not credential secrecy.
- The OpenAPI `TrackerKeyAuth` scheme is wired onto the `/events` and
  `/events/batch` operations as the documented mechanism (it describes a body
  field, not an `Authorization` header). The `pid`/`proj_` identifier is
  removed from the tracker taxonomy in favour of `site_key`.

### 2. Dashboard authentication — session-based, email + password

- The Statflow dashboard uses **session-based authentication**: a short-lived
  JWT access token plus a rotating `HttpOnly` refresh-token cookie, exactly as
  `api/README.md §2.1` describes.
- **v1 ships email + password authentication.** `POST /auth/login`,
  `POST /auth/register`, `POST /auth/forgot-password`,
  `POST /auth/reset-password`, and `PUT /users/me/password` are all in scope
  and in OpenAPI. `password_hash` on `users` is **populated** (bcrypt); the
  schema comment claiming it is always NULL is corrected.
- **OAuth is deferred.** GitHub/Google OAuth and magic-link are post-v1. Screen
  7 shows email+password, register, and forgot-password only; the OAuth buttons
  are removed from the v1 screen.
- Password policy: one minimum length everywhere — **12 characters** — for
  login, register, and password change.

### 3. Team roles — four roles

Statflow has **four** team roles: `owner`, `admin`, `editor`, `viewer`. This
matches the PostgreSQL enum, `postgres.md`, and the roadmap. The OpenAPI
`TeamRole` enum, the `api/README.md` role table, and the design-system Screen 6
role list are corrected to include `editor`.

| Role   | Capabilities |
|--------|--------------|
| owner  | Everything, plus billing and team deletion. One per team. |
| admin  | Site management, member invite/remove, API-key management, all data. |
| editor | Create/edit sites, goals, funnels, segments, reports. No member or billing management. |
| viewer | Read-only: dashboards, analytics, saved reports. No mutations. |

### 4. API-key scopes — granular `resource:action`

- API keys use the granular `resource:action` scope vocabulary
  (`analytics:read`, `sites:read`, `sites:write`, `reports:read`,
  `reports:write`). The coarse PostgreSQL `api_key_scope` enum is **replaced**
  by a `scopes TEXT[]` column; the `api_keys` table gains a `site_ids UUID[]`
  column (empty array = all sites in the team) and is no longer FK-bound to a
  single site.
- These programmatic keys are **distinct from the ingestion `stk_` site key**.
  Programmatic keys are prefixed `sfk_live_` / `sfk_test_`, are secret, and are
  shown once at creation.
- One hash algorithm: **SHA-256** (already the PostgreSQL choice and adequate
  for a high-entropy random key; the README's BLAKE2b mention is corrected).
- One prefix length: the stored/displayed `key_prefix` is **12 characters**.

## Consequences

- The ingestion endpoint has exactly one credential, one transport, and one
  storage column. `TrackerKeyAuth` is now used, not dead.
- The first-party proxy story is internally consistent: it is a transparent
  pass-through, not a secret-injection layer.
- Identity-context work (sites/teams/users) is unblocked: roles, auth method,
  and key model are frozen.
- The PostgreSQL schema changes: `users.password_hash` is used; `api_keys`
  loses its single `site_id` FK and `scope` enum, gains `site_ids UUID[]` and
  `scopes TEXT[]`; `api_key_scope` enum is dropped.
- OAuth deferral means enterprise SSO (roadmap 3.32) and OAuth land together
  later, with their own ADR and endpoints, without reworking the v1 surface.
