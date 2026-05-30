# Statflow API Design Principles

This document defines the foundational conventions for every endpoint in the Statflow REST API. All implementation work must conform to these decisions before any PR is merged.

---

## Table of Contents

1. [Versioning](#1-versioning)
2. [Authentication and Authorization](#2-authentication-and-authorization)
3. [Pagination](#3-pagination)
4. [Filtering and Sorting](#4-filtering-and-sorting)
5. [Rate Limiting](#5-rate-limiting)
6. [Error Model](#6-error-model)
7. [Idempotency](#7-idempotency)
8. [CORS](#8-cors)
9. [Content Negotiation](#9-content-negotiation)
10. [Request and Response Conventions](#10-request-and-response-conventions)
11. [Deprecation Policy](#11-deprecation-policy)

---

## 1. Versioning

### Strategy

Statflow uses **URI path versioning**. Every public endpoint is prefixed with `/api/v{major}`. The current stable version is `v1`.

```
https://{host}/api/v1/...
```

A new major version is introduced only for breaking changes. Additive changes (new optional fields, new endpoints, new filter operators) are non-breaking and do not require a new version.

Breaking changes include:

- Removing or renaming a field in a response body.
- Changing the type of an existing field.
- Removing an endpoint.
- Changing the semantics of an existing parameter (e.g., redefining what `date_from` means).

### Version Lifecycle

| Version | Status      | Sunset date            |
|---------|-------------|------------------------|
| v1      | Stable      | N/A                    |

When a new major version is released, the previous version receives a minimum **12-month** deprecation window. A `Deprecation` header (RFC 8594) and `Sunset` header (RFC 8594) are added to all responses from a deprecated version.

```
Deprecation: true
Sunset: Sat, 31 Dec 2027 23:59:59 GMT
Link: <https://{host}/api/v2/>; rel="successor-version"
```

---

## 2. Authentication and Authorization

Statflow has three distinct authentication surfaces, each serving a different client type. All authentication traffic must occur over HTTPS. Credentials must never appear in query strings or URL fragments.

### 2.1 Session Authentication — Dashboard SPA

Used exclusively by the Vue 3 dashboard. Authentication is stateless on the API side: after a successful login the server issues a short-lived **JWT access token** (15 minutes) and a longer-lived **opaque refresh token** (30 days, stored in an `HttpOnly`, `SameSite=Strict`, `Secure` cookie).

Flow:

1. `POST /api/v1/auth/login` — credential check, returns access token in response body and sets refresh token cookie.
2. Client attaches access token as `Authorization: Bearer {token}` on every request.
3. On 401, client transparently calls `POST /api/v1/auth/refresh` — server validates the cookie, rotates the refresh token, issues a new access token.
4. `POST /api/v1/auth/logout` — invalidates the refresh token server-side (token blacklist in Redis), clears the cookie.

Access tokens are signed with ES256 (ECDSA P-256). The public key is available at `GET /api/v1/auth/.well-known/jwks.json` to allow future microservice verification without shared secrets.

JWT payload claims:

| Claim | Value                                                    |
|-------|----------------------------------------------------------|
| `sub` | User UUID                                                |
| `iat` | Issued-at timestamp                                      |
| `exp` | Expiry (iat + 900 seconds)                               |
| `jti` | Unique token ID (for revocation checks)                  |
| `teams` | Array of `{ team_id, role }` objects                  |

v1 ships **email + password** authentication. `POST /api/v1/auth/register`,
`POST /api/v1/auth/login`, `POST /api/v1/auth/forgot-password`,
`POST /api/v1/auth/reset-password`, and `PUT /api/v1/users/me/password` are all
in scope. OAuth / magic-link / SSO are **deferred post-v1** (see ADR-0009).
Password minimum length is **12 characters** on every endpoint that accepts a
password (register, login, change, reset).

### 2.2 API Key Authentication — Programmatic / Integrations

Used by CI pipelines, third-party integrations, or headless scripts that consume the Analytics, Reporting, or Sites APIs. These are **secret** programmatic keys — distinct from the public tracker key in §2.3.

- Keys are prefixed: `sfk_live_` for production, `sfk_test_` for sandbox.
- Transmitted as `Authorization: Bearer sfk_live_...` header.
- Keys are hashed with **SHA-256** before storage. The raw key is shown once at creation.
- Keys carry an explicit **scope list** drawn from the fixed vocabulary `analytics:read`, `sites:read`, `sites:write`, `reports:read`, `reports:write`.
- Keys are **team-scoped** and may be restricted to a subset of the team's `site_ids` (empty = all sites in the team).
- The stored/displayed `key_prefix` is the first **12 characters** of the key.
- No expiry by default; callers may set an optional `expires_at`.

### 2.3 Tracker Key Authentication — Event Ingestion

Used by the JavaScript tracker embedded in customer sites. The tracker key (`stk_`) identifies the site and authorises event submission. It is **public by design** — it must be embedded in client-side JS — and therefore carries no privileges beyond writing events to the ingestion pipeline. See ADR-0009.

- Transmitted **only** as the `site_key` field in the event payload body — never a header — so that `navigator.sendBeacon` works without a CORS preflight.
- The server validates the key against three controls: (1) the key maps to a known, enabled site; (2) the request `Origin` is in the site's `allowed_domains` allowlist; (3) the per-site ingestion rate limit is not exceeded.
- The tracker key grants write-only access to `POST /api/v1/events` and `POST /api/v1/events/batch` exclusively.
- There is **no secret ingestion token.** The first-party proxy (anti-adblock) forwards the public `site_key` already present in the body; it injects no credential.

### 2.4 Roles and Permissions

Statflow has **four** team roles (ADR-0009):

| Role    | Scope                                                                           |
|---------|---------------------------------------------------------------------------------|
| Owner   | Full control: billing, team management, site deletion, all data access. One per team. |
| Admin   | Site management, member invite/remove, manage API keys for the team, all data.  |
| Editor  | Create and edit sites, goals, funnels, segments, and reports. No member or billing management. |
| Viewer  | Read-only: dashboards, analytics data, saved reports. Cannot modify settings.   |

Authorisation is enforced at the command/query handler level via Symfony Security voters. Every handler declares the required permission; the voter resolves team membership from the JWT or API key scopes.

---

## 3. Pagination

Statflow uses **cursor-based pagination** for all list endpoints. Offset pagination is explicitly avoided because ClickHouse queries at large offsets are expensive and result sets can shift between pages as new data arrives.

### Cursor Pagination

Request parameters:

| Parameter  | Type    | Default | Description                                          |
|------------|---------|---------|------------------------------------------------------|
| `cursor`   | string  | —       | Opaque base64url cursor from a previous response.    |
| `limit`    | integer | 20      | Items per page. Maximum: 100.                        |
| `direction`| string  | `next`  | `next` or `prev` for bidirectional navigation.       |

Response envelope:

```json
{
  "data": [ ... ],
  "pagination": {
    "next_cursor": "eyJpZCI6MTIzfQ",
    "prev_cursor": "eyJpZCI6OTl9",
    "has_next": true,
    "has_prev": true,
    "limit": 20
  }
}
```

When there are no more pages in a direction, the corresponding cursor field is `null` and the `has_*` flag is `false`.

Cursors encode the sort key(s) and direction, are opaque to clients, and are valid for 24 hours. Clients must not construct or modify cursor values.

### Analytics Query Responses

Analytics aggregate endpoints (e.g., top pages, time series) return **flat arrays** without pagination cursors because the result set is bounded by the query date range and pre-aggregation. Very large exports use the Reporting export flow instead.

---

## 4. Filtering and Sorting

### Filtering

Filters are expressed as **query parameters** for simple cases and as a **JSON body field** (`filters`) for complex boolean expressions in analytics queries.

Simple filter format (list endpoints):

```
GET /api/v1/sites?search=acme&status=active
```

Analytics filter format (request body):

```json
{
  "filters": [
    { "property": "pathname", "operator": "contains", "value": "/blog" },
    { "property": "country", "operator": "eq", "value": "FR" },
    { "property": "device_type", "operator": "in", "value": ["mobile", "tablet"] }
  ],
  "filter_combination": "and"
}
```

Supported operators:

| Operator      | Applicable types       |
|---------------|------------------------|
| `eq`          | string, number, bool   |
| `neq`         | string, number, bool   |
| `in`          | string, number         |
| `not_in`      | string, number         |
| `contains`    | string                 |
| `not_contains`| string                 |
| `starts_with` | string                 |
| `gt`          | number, datetime       |
| `gte`         | number, datetime       |
| `lt`          | number, datetime       |
| `lte`         | number, datetime       |

`filter_combination` defaults to `"and"`. A top-level `"or"` is supported. Nested boolean groups are not in v1 scope.

Filterable properties across analytics queries:

`pathname`, `hostname`, `referrer_domain`, `utm_source`, `utm_medium`, `utm_campaign`, `country`, `region`, `city`, `device_type`, `browser`, `os`, `language`, `screen_width`, `entry_page`, `exit_page`, `event_name`, `session_id`, and any `custom_properties.*` key.

### Sorting

Sort is expressed via `sort_by` and `sort_order` query parameters on list endpoints:

```
GET /api/v1/sites?sort_by=created_at&sort_order=desc
```

Analytics result tables (top pages, top sources, etc.) accept `sort_by` and `sort_order` in the request body alongside `filters`.

`sort_order` values: `asc`, `desc` (default: `desc`).

---

## 5. Rate Limiting

Rate limits are applied per authentication surface and are enforced in a Redis sliding-window counter.

| Surface                    | Limit                        | Window    |
|----------------------------|------------------------------|-----------|
| Ingestion (`/api/v1/events`) — per site key | 10 000 req | 1 minute |
| Ingestion batch             | 500 req                      | 1 minute  |
| Dashboard API (session JWT) | 1 000 req                    | 1 minute  |
| API key (programmatic)      | 500 req                      | 1 minute  |
| Auth endpoints (login, refresh) | 20 req                  | 1 minute  |

Response headers on every request:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 743
X-RateLimit-Reset: 1716840600
```

When the limit is exceeded, the server responds with `429 Too Many Requests` and a `Retry-After` header (seconds until the window resets). The response body follows the RFC 9457 Problem Details format (see section 6).

Ingestion is designed to degrade gracefully: once the per-site ingestion limit is exceeded, the tracker receives a `429` but the end-user experience on the tracked site is unaffected.

---

## 6. Error Model

Statflow uses **RFC 9457 Problem Details** (`application/problem+json`) for all error responses. This provides machine-readable, structured errors across every context.

### Structure

```json
{
  "type": "https://statflow.io/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request payload failed schema validation.",
  "instance": "/api/v1/sites/abc123",
  "errors": [
    {
      "field": "data_retention_days",
      "code": "must_be_positive_integer",
      "message": "data_retention_days must be a positive integer between 30 and 730."
    }
  ],
  "trace_id": "01HXK2Q4B7T8NMPZW6RDYGJ5FM"
}
```

### Fields

| Field      | Required | Description                                                                   |
|------------|----------|-------------------------------------------------------------------------------|
| `type`     | Yes      | Absolute URI identifying the error type. Dereferenceable to documentation.   |
| `title`    | Yes      | Short, human-readable summary. Does not change between occurrences.           |
| `status`   | Yes      | HTTP status code (integer).                                                   |
| `detail`   | No       | Human-readable explanation specific to this occurrence.                       |
| `instance` | No       | URI identifying the specific request that triggered the error.                |
| `errors`   | No       | Array of field-level validation errors (see schema below).                    |
| `trace_id` | Yes      | Ulid for cross-service correlation. Logged server-side. Include in bug reports. |

The `errors` extension field uses the following per-field schema:

```json
{
  "field": "pathname",
  "code": "required",
  "message": "pathname is required."
}
```

### HTTP Status Code Usage

| Code | Meaning                                                                        |
|------|--------------------------------------------------------------------------------|
| 200  | OK — successful read or query.                                                 |
| 201  | Created — resource created (sites, API keys, saved reports).                  |
| 204  | No Content — successful write with no response body (event ingestion).        |
| 400  | Bad Request — malformed JSON, unknown fields, type coercion failure.          |
| 401  | Unauthorized — missing or invalid authentication credential.                  |
| 403  | Forbidden — authenticated but insufficient permission for this resource.      |
| 404  | Not Found — resource does not exist or is not visible to the caller.          |
| 409  | Conflict — duplicate resource (e.g., site domain already registered).         |
| 410  | Gone — resource was permanently deleted.                                       |
| 422  | Unprocessable Entity — syntactically valid payload that fails business rules. |
| 429  | Too Many Requests — rate limit exceeded.                                       |
| 500  | Internal Server Error — unexpected server fault.                               |
| 503  | Service Unavailable — ClickHouse or PostgreSQL dependency unavailable.        |

See `error-catalog.md` for the full list of typed error URIs.

---

## 7. Idempotency

### Event Ingestion

The ingestion endpoint (`POST /api/v1/events`) is the highest-throughput surface. Trackers may retry on network failure, and CDN edge workers may replay events. Duplicate suppression is therefore critical.

Every event payload carries an `event_id` field — a **client-generated UUID v4** produced by the tracker's `core/ids.ts` module. The ingestion pipeline deduplicates on `(site_key, event_id)` within a 24-hour rolling window using a Redis Bloom filter (false-positive rate < 0.1%).

Rules:

1. The tracker generates a unique `event_id` per event and includes it in the wire payload (`eid`). It is part of the canonical event contract — see `docs/data-model/event-contract.md`.
2. Replaying the same `event_id` within 24 hours results in a `204` (silent discard, not an error).
3. The `event_id` is propagated through the Redis Streams buffer and stored in the ClickHouse `events.event_id` column for audit and late-arrival deduplication.

### Mutating API Endpoints

For non-ingestion mutating operations (creating sites, API keys, etc.), clients may supply an `Idempotency-Key` request header (UUID v4 or ULID, max 128 chars). The server caches the response for 24 hours keyed on `(endpoint, Idempotency-Key, user_id)`. Replaying the request within the window returns the cached response with `Idempotency-Replayed: true` header.

---

## 8. CORS

### Ingestion Endpoint

`POST /api/v1/events` must be callable from any origin because the JS tracker is embedded on third-party sites.

- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: POST, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type`
- No credentials (`withCredentials` is never needed for ingestion).
- Preflight cache: `Access-Control-Max-Age: 86400`.

The event payload uses `Content-Type: application/json`; because this triggers a preflight, the wide `Max-Age` is important for tracker performance.

An alternative `text/plain` content-type mode exists for beacon-based submission (navigator.sendBeacon) to avoid preflights. In this mode the payload is still JSON but the `Content-Type` header is set to `text/plain`.

### Dashboard API

All other `/api/v1/` endpoints are restricted to the configured dashboard origin(s). The allowed origins are stored per-deployment in environment configuration.

- `Access-Control-Allow-Origin: {DASHBOARD_ORIGIN}` (single specific origin, not wildcard)
- `Access-Control-Allow-Credentials: true`
- `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Authorization, Content-Type, Idempotency-Key`
- `Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Deprecation, Sunset`

---

## 9. Content Negotiation

### Request

Dashboard and programmatic request bodies use `Content-Type: application/json; charset=utf-8`. The **ingestion endpoint** (`POST /api/v1/events`, `/events/batch`) accepts both `application/json` and `text/plain` with a JSON body, and the browser tracker uses `text/plain` on **both** its `fetch` and `sendBeacon` paths — this removes the CORS preflight from every ingestion request, not just beacon flushes (see `docs/data-model/event-contract.md §3` and ADR-0007). Requests with any other `Content-Type` receive `415 Unsupported Media Type`.

### Response

All responses use `Content-Type: application/json; charset=utf-8` except:

- Error responses: `Content-Type: application/problem+json; charset=utf-8`
- Data export (CSV): `Content-Type: text/csv; charset=utf-8` with `Content-Disposition: attachment; filename="..."`
- Data export (JSON lines): `Content-Type: application/x-ndjson; charset=utf-8`

Clients that include `Accept: application/problem+json` in non-error requests receive normal JSON; the problem type is returned only on 4xx/5xx.

### Compression

Responses larger than 1 KB are gzip-compressed when the client sends `Accept-Encoding: gzip`. Ingestion responses are always `204 No Content` (no body) and are never compressed.

The browser tracker does **not** gzip request bodies — it relies on small `text/plain` batches. The ingestion endpoint MAY accept `Content-Encoding: gzip` on request bodies for server-side integrations; the browser tracker never sets it.

---

## 10. Request and Response Conventions

### Timestamps

All timestamps are **ISO 8601 with UTC offset**, e.g., `"2025-06-15T14:30:00Z"`. Fractional seconds are included where precision matters (event timestamps use millisecond precision: `"2025-06-15T14:30:00.123Z"`). Clients submitting timestamps in non-UTC zones must include the offset; the server normalises to UTC on ingestion.

### UUIDs and IDs

Statflow uses **UUID** (128-bit, `gen_random_uuid()`) for all internally generated identifiers — every PostgreSQL primary key and every `site_id` / `team_id` / resource ID in the API. They are represented as standard 36-character hyphenated UUID strings in the API. The client-generated `event_id` on the ingestion path is a **UUID v4** produced by the tracker. There is no ULID anywhere in the platform; earlier ULID references in spec drafts are superseded by this rule and by the PostgreSQL schema.

### Field Naming

Snake_case is used consistently for all request and response field names. No camelCase, no kebab-case.

### Null vs Absent

Optional fields that have no value are **omitted** from responses (not set to `null`), unless the field semantically represents an explicitly cleared value (e.g., `expires_at: null` on an API key means "never expires").

### Boolean Fields

Represented as JSON booleans (`true`/`false`). The strings `"true"`, `"1"`, `"yes"` are not accepted.

### Numbers

Floating-point metrics (e.g., `bounce_rate`, `avg_duration`) are returned as JSON numbers with at most 4 decimal places. Integer counts are returned as JSON integers.

---

## 11. Deprecation Policy

When a field, parameter, or endpoint is deprecated:

1. A `Deprecation: true` header is added to all responses from that endpoint.
2. A `Link` header points to migration documentation.
3. The field/parameter is documented as deprecated in the OpenAPI spec with `deprecated: true` and an `x-deprecation-note`.
4. A minimum 6-month notice period applies before removal.
5. Removal only happens in a new major API version.

Breaking changes to the ingestion endpoint have a **minimum 12-month** notice period given the operational complexity of updating deployed trackers.
