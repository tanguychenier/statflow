# Statflow Error Catalog

This document is the authoritative reference for all typed error URIs used in RFC 9457 Problem Details responses. Every `type` URI below is a stable identifier; the path corresponds to a documentation page on the Statflow developer portal.

---

## Error Model Summary

Statflow error responses always use `Content-Type: application/problem+json`. The shape is:

```json
{
  "type": "https://statflow.io/errors/{slug}",
  "title": "...",
  "status": 400,
  "detail": "...",
  "instance": "/api/v1/...",
  "trace_id": "01HXK2Q4B7T8NMPZW6RDYGJ5FM",
  "errors": []
}
```

The `trace_id` field is always present. It is a server-generated ULID that is written to structured logs with the full request context. Include it when filing bug reports or opening support tickets.

The `errors` array is present and non-empty only for validation-class errors (400, 422). Each item follows the schema:

```json
{
  "field": "utm_source",
  "code": "max_length_exceeded",
  "message": "utm_source must not exceed 200 characters."
}
```

---

## Error Catalog

### Authentication Errors

#### `authentication-required`

| Field   | Value                                               |
|---------|-----------------------------------------------------|
| Type    | `https://statflow.io/errors/authentication-required` |
| Title   | Authentication Required                             |
| Status  | 401                                                 |

The request did not include an `Authorization` header or a valid session. Obtain a token via `POST /api/v1/auth/login` or `POST /api/v1/auth/refresh`.

---

#### `invalid-credentials`

| Field   | Value                                          |
|---------|------------------------------------------------|
| Type    | `https://statflow.io/errors/invalid-credentials` |
| Title   | Invalid Credentials                            |
| Status  | 401                                            |

The supplied email/password combination does not match any user account. Does not distinguish between "user not found" and "wrong password" to prevent user enumeration.

---

#### `token-expired`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/token-expired` |
| Title   | Token Expired                            |
| Status  | 401                                      |

The JWT access token has expired. Call `POST /api/v1/auth/refresh` with the refresh token cookie to obtain a new access token.

---

#### `token-revoked`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/token-revoked` |
| Title   | Token Revoked                            |
| Status  | 401                                      |

The token has been explicitly revoked (logout, password change, or admin revocation). The client must re-authenticate.

---

#### `invalid-api-key`

| Field   | Value                                      |
|---------|--------------------------------------------|
| Type    | `https://statflow.io/errors/invalid-api-key` |
| Title   | Invalid API Key                            |
| Status  | 401                                        |

The provided API key (`sfk_*`) is not recognised, has been revoked, or is expired.

---

#### `invalid-tracker-key`

| Field   | Value                                         |
|---------|-----------------------------------------------|
| Type    | `https://statflow.io/errors/invalid-tracker-key` |
| Title   | Invalid Tracker Key                           |
| Status  | 401                                           |

The `site_key` in the event payload does not match any registered site, or the site has been disabled.

---

#### `origin-not-allowed`

| Field   | Value                                        |
|---------|----------------------------------------------|
| Type    | `https://statflow.io/errors/origin-not-allowed` |
| Title   | Origin Not Allowed                           |
| Status  | 401                                          |

The `Origin` header of the ingestion request does not appear in the site's `allowed_domains` list. Configure allowed domains in site settings.

---

### Authorization Errors

#### `permission-denied`

| Field   | Value                                      |
|---------|--------------------------------------------|
| Type    | `https://statflow.io/errors/permission-denied` |
| Title   | Permission Denied                          |
| Status  | 403                                        |

The authenticated identity does not have the required role or scope for this operation. The `detail` field indicates the required permission.

---

#### `api-key-scope-insufficient`

| Field   | Value                                               |
|---------|-----------------------------------------------------|
| Type    | `https://statflow.io/errors/api-key-scope-insufficient` |
| Title   | API Key Scope Insufficient                          |
| Status  | 403                                                 |

The API key is valid but lacks the required scope for this endpoint. Example: a key with `analytics:read` scope attempting `POST /api/v1/sites`.

---

#### `site-access-denied`

| Field   | Value                                       |
|---------|---------------------------------------------|
| Type    | `https://statflow.io/errors/site-access-denied` |
| Title   | Site Access Denied                          |
| Status  | 403                                         |

The API key is restricted to specific site IDs and the requested `site_id` is not in the allowlist.

---

### Resource Errors

#### `not-found`

| Field   | Value                               |
|---------|-------------------------------------|
| Type    | `https://statflow.io/errors/not-found` |
| Title   | Not Found                           |
| Status  | 404                                 |

The requested resource does not exist or is not accessible to the caller. This response is also returned for resources the caller is not permitted to see (to avoid existence disclosure).

---

#### `resource-deleted`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/resource-deleted` |
| Title   | Resource Deleted                         |
| Status  | 410                                      |

The resource existed but has been permanently deleted. The `instance` URI identifies the deleted resource.

---

#### `conflict`

| Field   | Value                               |
|---------|-------------------------------------|
| Type    | `https://statflow.io/errors/conflict` |
| Title   | Conflict                            |
| Status  | 409                                 |

A resource with conflicting unique attributes already exists. The `detail` field indicates which attribute conflicts. Common cases: duplicate site domain, duplicate team name.

---

### Validation Errors

#### `validation-failed`

| Field   | Value                                      |
|---------|--------------------------------------------|
| Type    | `https://statflow.io/errors/validation-failed` |
| Title   | Validation Failed                          |
| Status  | 422                                        |

One or more fields in the request payload failed validation. The `errors` array lists each field, its error code, and a human-readable message.

Common per-field codes:

| Code                     | Meaning                                              |
|--------------------------|------------------------------------------------------|
| `required`               | Field is required but was not provided.              |
| `invalid_type`           | Field value is of the wrong JSON type.               |
| `max_length_exceeded`    | String value exceeds maximum length.                 |
| `min_length_not_met`     | String value is shorter than minimum length.         |
| `invalid_format`         | Value does not match expected format (UUID, URL...). |
| `invalid_enum_value`     | Value is not one of the allowed enum members.        |
| `out_of_range`           | Numeric value is outside the allowed range.          |
| `invalid_date_range`     | `date_from` is after `date_to`, or range exceeds maximum. |
| `unknown_property`       | The payload contains a field name not in the schema. |
| `must_be_positive_integer` | Value must be a positive (> 0) integer.            |

---

#### `malformed-json`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/malformed-json` |
| Title   | Malformed JSON                           |
| Status  | 400                                      |

The request body is not syntactically valid JSON. Check for unescaped characters, trailing commas, or encoding issues.

---

#### `unsupported-content-type`

| Field   | Value                                             |
|---------|---------------------------------------------------|
| Type    | `https://statflow.io/errors/unsupported-content-type` |
| Title   | Unsupported Content Type                          |
| Status  | 415                                               |

The `Content-Type` header is not `application/json` (or `text/plain` for beacon mode on the ingestion endpoint).

---

#### `invalid-filter`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/invalid-filter` |
| Title   | Invalid Filter                           |
| Status  | 422                                      |

A filter in the `filters` array references an unknown property, an unsupported operator for that property's type, or an incompatible value type. The `errors` array includes the index and details of the offending filter.

---

#### `invalid-date-range`

| Field   | Value                                       |
|---------|---------------------------------------------|
| Type    | `https://statflow.io/errors/invalid-date-range` |
| Title   | Invalid Date Range                          |
| Status  | 422                                         |

`date_from` is after `date_to`, the range spans more than the maximum allowed days for the requested granularity, or the dates are in an unrecognised format.

---

#### `funnel-steps-invalid`

| Field   | Value                                          |
|---------|------------------------------------------------|
| Type    | `https://statflow.io/errors/funnel-steps-invalid` |
| Title   | Funnel Steps Invalid                           |
| Status  | 422                                            |

The `steps` array of a saved funnel is empty, contains fewer than 2 steps, contains more than 16 steps (the v1 limit — see `feature-roadmap.md 2.11`), or includes a step with an invalid trigger or filter expression.

---

### Rate Limiting Errors

#### `rate-limit-exceeded`

| Field   | Value                                        |
|---------|----------------------------------------------|
| Type    | `https://statflow.io/errors/rate-limit-exceeded` |
| Title   | Rate Limit Exceeded                          |
| Status  | 429                                          |

The caller has exceeded the request quota for the current sliding window. The `Retry-After` response header indicates the number of seconds to wait before retrying. The `detail` field specifies the limit surface (e.g., "per-site ingestion limit").

---

### Ingestion-Specific Errors

#### `event-payload-too-large`

| Field   | Value                                            |
|---------|--------------------------------------------------|
| Type    | `https://statflow.io/errors/event-payload-too-large` |
| Title   | Event Payload Too Large                          |
| Status  | 413                                              |

The event payload (single or batch) exceeds the maximum allowed size. Single event: 16 KB. Batch: 256 KB or 100 events, whichever is reached first. Reduce `custom_properties` size or split the batch.

---

#### Partial batch results (not an error type)

`POST /api/v1/events/batch` does **not** return an RFC 9457 Problem Details
object when some events are rejected. A partially-successful batch is a normal
success: the endpoint returns **`200 OK`** with an `application/json` body
(schema `BatchResultResponse`) listing per-event status. A fully-successful
batch returns `204 No Content`. Only a malformed envelope returns a `4xx`
Problem Details response.

```json
{
  "accepted": 2,
  "rejected": 1,
  "results": [
    { "index": 0, "status": "accepted" },
    { "index": 1, "status": "rejected", "code": "validation-failed", "errors": [ { "field": "event_name", "code": "required" } ] },
    { "index": 2, "status": "accepted" }
  ]
}
```

Successfully accepted events are not replayed; only the failed ones should be
retried (with the same `event_id`, which guarantees idempotency).

There is no `207`-status error and no `batch-partially-failed` error type:
`207 Multi-Status` is a WebDAV success code and is not used by this JSON API.

---

### Server Errors

#### `internal-error`

| Field   | Value                                     |
|---------|-------------------------------------------|
| Type    | `https://statflow.io/errors/internal-error` |
| Title   | Internal Server Error                     |
| Status  | 500                                       |

An unexpected fault occurred on the server. The `trace_id` is logged with full context. No internal stack trace or system details are included in the response body.

---

#### `dependency-unavailable`

| Field   | Value                                             |
|---------|---------------------------------------------------|
| Type    | `https://statflow.io/errors/dependency-unavailable` |
| Title   | Dependency Unavailable                            |
| Status  | 503                                               |

A required upstream dependency (ClickHouse, PostgreSQL, or Redis) is not reachable or is returning errors. Analytics query endpoints return this when ClickHouse is unavailable. The `detail` field identifies the dependency. The response includes `Retry-After: 30`.

---

#### `query-timeout`

| Field   | Value                                    |
|---------|------------------------------------------|
| Type    | `https://statflow.io/errors/query-timeout` |
| Title   | Query Timeout                            |
| Status  | 504                                      |

An analytics query exceeded the maximum execution time (default: 30 seconds). The query was cancelled server-side. Narrow the date range, reduce the number of filters, or use the async export endpoint for large queries.

---

## Error Type Quick Reference

| Slug                          | HTTP |
|-------------------------------|------|
| `authentication-required`     | 401  |
| `invalid-credentials`         | 401  |
| `token-expired`               | 401  |
| `token-revoked`               | 401  |
| `invalid-api-key`             | 401  |
| `invalid-tracker-key`         | 401  |
| `origin-not-allowed`          | 401  |
| `permission-denied`           | 403  |
| `api-key-scope-insufficient`  | 403  |
| `site-access-denied`          | 403  |
| `not-found`                   | 404  |
| `resource-deleted`            | 410  |
| `conflict`                    | 409  |
| `validation-failed`           | 422  |
| `malformed-json`              | 400  |
| `unsupported-content-type`    | 415  |
| `invalid-filter`              | 422  |
| `invalid-date-range`          | 422  |
| `funnel-steps-invalid`        | 422  |
| `rate-limit-exceeded`         | 429  |
| `event-payload-too-large`     | 413  |
| `internal-error`              | 500  |
| `dependency-unavailable`      | 503  |
| `query-timeout`               | 504  |
