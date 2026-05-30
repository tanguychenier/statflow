# Statflow — Canonical Event Contract

**Status:** Frozen (normative)
**Last updated:** 2026-05-16
**Authority:** This document is the single source of truth for the Statflow
event payload. The OpenAPI `EventPayload` schema, the tracker wire format
(`docs/tracker/event-taxonomy.md`), and the ClickHouse `events` table
(`docs/data-model/clickhouse-schema.sql`) are all defined to be consistent with
it. See [ADR-0007](../adr/0007-canonical-event-contract.md).

---

## 1. Two representations, one model

| Representation | Field names | Used by |
|----------------|-------------|---------|
| **Canonical**  | long, descriptive (`event_name`, `viewport_width`) | API request schema, ClickHouse columns, all backend code, the Stats API |
| **Wire**       | short keys (`e`, `vw`) | the browser tracker's HTTP transport only |

The browser tracker emits the **wire** form to minimise payload size. The
Ingestion context performs exactly one transformation — **wire → canonical** —
on receipt, before any validation, enrichment, or buffering. Nothing downstream
of the ingestion handler ever sees a short key.

Server-side integrations send the **canonical** form directly (they have no
payload-size pressure) and skip the compaction step.

---

## 2. Wire ↔ canonical field mapping

The tracker assembles the wire envelope automatically. Product engineers only
supply the event name and custom properties.

| Wire key | Canonical name        | Type (canonical) | Required | Notes |
|----------|-----------------------|------------------|:--------:|-------|
| `eid`    | `event_id`            | string (UUIDv4)  | yes | client-generated; deduplication key |
| `k`      | `site_key`            | string (`stk_…`) | yes | public site key (ADR-0009) |
| `e`      | `event_name`          | string           | yes | from the §4 vocabulary |
| `ts`     | `timestamp`           | string (ISO-8601, ms) | yes | wire sends Unix ms **number**; normaliser converts to ISO-8601 UTC string |
| `seq`    | `seq`                 | integer          | yes | monotonic per session |
| `u`      | `url`                 | string (URI)     | yes | credentials stripped client-side |
| `p`      | `pathname`            | string           | yes | path component of `url` |
| `h`      | `hostname`            | string           | yes | host component of `url` |
| `r`      | `referrer`            | string (URI)     | no  | omitted if empty |
| `t`      | `title`               | string           | no  | `document.title` |
| `vw`     | `viewport_width`      | integer          | yes | CSS px |
| `vh`     | `viewport_height`     | integer          | yes | CSS px |
| `sw`     | `screen_width`        | integer          | yes | CSS px |
| `sh`     | `screen_height`       | integer          | yes | CSS px |
| `dpr`    | `device_pixel_ratio`  | number           | no  | rounded |
| `ct`     | `connection_type`     | string           | no  | NetworkInformation API `effectiveType` |
| `tv`     | `tracker_version`     | string           | yes | tracker build version |
| `props`  | `custom_properties`   | object           | no  | flat key/value map (see §6) |
| `b`      | `behavioral`          | object           | no  | behavioral signals (see §5) |

**Derived server-side, never on the wire** (see ADR-0008): `visitor_id`,
`session_id`, `country` / `region` / `city`, `referrer_source`, `device_type`,
`browser`, `browser_version`, `os`, `os_version`. The tracker does not send
these and the API request schema does not accept them.

**UTM fields** (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`,
`utm_content`) are parsed from `url` server-side; the wire format does not carry
them separately. Server-side integrations MAY supply them in the canonical
payload.

### 2.1 Timestamp normalisation

The wire `ts` is a Unix-millisecond **number** (smallest representation). The
ingestion normaliser converts it to an ISO-8601 UTC string with millisecond
precision (`2025-06-15T14:30:00.123Z`) for the canonical model and to
`DateTime64(3,'UTC')` for ClickHouse. The canonical/API representation is always
the ISO-8601 string.

### 2.2 Custom-property type coercion

`custom_properties` values may be string, number, or boolean in the canonical
model. The ClickHouse `events.properties` column is `Map(String, String)`;
numbers and booleans are coerced to their canonical string form by the batch
writer (`true`/`false` for booleans, the shortest round-trippable decimal for
numbers). This coercion is part of the batch writer's contract.

---

## 3. Batch envelope (unified)

Events are always transmitted in a batch envelope. There is one envelope, with
root key `events`:

```jsonc
{
  "events": [
    { "e": "pageview", /* ...wire event... */ },
    { "e": "click",    /* ...wire event... */ }
  ],
  "sent_at": 1747400012345,   // Unix ms when the batch was dispatched
  "sdk":     "tracker",
  "sdk_v":   "1.0.0"
}
```

- `POST /api/v1/events` accepts a single canonical event OR this envelope with
  one entry; `POST /api/v1/events/batch` accepts the envelope with up to 100
  entries.
- Transport: `Content-Type: text/plain`, JSON-encoded body, on **both** the
  `fetch` and `sendBeacon` paths (ADR-0007). The endpoint parses the body as
  JSON regardless of content type.
- Limits: 16 KB per single event, 256 KB or 100 events per batch.

---

## 4. Event-name vocabulary (closed)

This is the **one** vocabulary. The tracker, the API `event_name` field, and the
ClickHouse `event_name` column all use exactly these names.

| Event name | Milestone | Description |
|------------|:---------:|-------------|
| `pageview` | Jalon 1 | Hard-navigation page load. |
| `route_change` | Jalon 1 | SPA soft navigation (`pushState`/`replaceState`/`popstate`). |
| `engagement` | Jalon 1 | Active-engagement heartbeat (see §7). |
| `click` | Jalon 2 | Pointer interaction; carries `behavioral` signals. |
| `rage_click` | Jalon 2 | ≥ 3 clicks on the same target within the rage window (see §7). |
| `dead_click` | Jalon 2 | Click with no DOM/navigation/network effect. |
| `scroll_depth` | Jalon 2 | A scroll-depth threshold was crossed. |
| `form_focus` | Jalon 2 | First focus of a form. |
| `form_submit` | Jalon 2 | Form submit. |
| `form_abandon` | Jalon 2 | Focused form, left without submitting. |
| `element_visibility` | Jalon 2 | A tracked element entered the viewport. |
| `custom` | Jalon 2 | Product-defined event via `statflow.track()`. |
| `conversion` | Jalon 2 | Emitted server-side by the goal evaluator when an event matches a goal (see §8). |
| `web_vital_lcp` / `web_vital_cls` / `web_vital_inp` | Jalon 2 | Core Web Vitals. |
| `js_error` | Jalon 2 | Unhandled error / promise rejection. |
| `heatmap_batch` | Jalon 3 | Batched mouse-move points from the lazy heatmap module. |

Notes:

- The previously conflicting `scroll` (OpenAPI / ClickHouse) vs `scroll_depth`
  (taxonomy) is resolved: the name is **`scroll_depth`**. The
  `scroll_depth_stats_mv` filter is `WHERE event_name = 'scroll_depth'`.
- `conversion` is **not** emitted by the tracker. The ingestion/analytics layer
  derives it from a goal definition (see §8). `source_stats.conversions` counts
  `event_name = 'conversion'`.
- `web_vital_*` and `js_error` are collected by the Jalon-2 autocapture tracker
  but, until dedicated rollup tables exist, are stored only in the `events` row
  (their structured fields land in `custom_properties`). They are valid event
  names so the data is never rejected.
- `heatmap_batch` is a Jalon-3 event; its `points` array is stored in
  `custom_properties` and is consumed by the Jalon-3 heatmap pipeline, not by
  `heatmap_stats_mv` (which aggregates `click` events — see §5).

---

## 5. Behavioral signals (`behavioral` / wire `b`)

Attached to `click`, `rage_click`, `dead_click`, `scroll_depth`, and
`engagement` events.

| Wire | Canonical | ClickHouse column | Type | Notes |
|------|-----------|-------------------|------|-------|
| `cx` | `click_x` | `click_x` | integer | **document-relative** X, CSS px (`clientX + scrollX`) |
| `cy` | `click_y` | `click_y` | integer | **document-relative** Y, CSS px |
| `cxp`| `click_x_pct` | — (derived) | number | X as % of document width |
| `cyp`| `click_y_pct` | — (derived) | number | Y as % of document height |
| `etag` | `element_tag` | `element_text`/selector group | string | tag name |
| `etxt` | `element_text` | `element_text` | string | sanitised, ≤ 128 chars |
| `esel` | `element_selector` | `element_selector` | string | sanitised, ≤ 512 chars |
| `eid` | `element_id` | — | string | element `id` if present |
| `sd` | `scroll_depth_pct` | `scroll_depth_pct` | integer | 0–100 |
| `sdpx` | `scroll_depth_px` | `scroll_depth_px` | integer | absolute scroll offset |
| `em` | `engagement_time_ms` | `engagement_time_ms` | integer | active ms since last heartbeat |
| `rc` | `is_rage_click` | `is_rage_click` | boolean | tracker-set; backend-validated |

### 5.1 Coordinate model — one origin

Click coordinates are **document-relative** end to end: tracker, API, and the
`events.click_x`/`click_y` columns all use document-relative pixels
(`clientX + scrollX`). The viewport-relative description in the ClickHouse
comments is corrected.

The `click_tx` / `click_ty` element-relative columns are **removed** from the
ClickHouse `events` table: no tracker or API field ever produced them, so they
could only ever be NULL. Element-level heatmaps, if built, will use
`element_selector`.

Because coordinates are document-relative, the `heatmap_stats_mv` normalisation
divides by **document width**, not viewport width. The percentage fields
(`click_x_pct`) are the stable cross-resolution representation and are the basis
for the heatmap grid; absolute `click_x`/`click_y` are retained for drill-down.

---

## 6. Custom properties

- `custom_properties` is a flat object: keys match `[a-z0-9_]{1,50}`, values are
  string / number / boolean (no nested objects).
- Maximum 20 keys per event; total serialised size ≤ 4 KB.
- Nested objects are rejected client-side by the tracker and by API validation.
- E-commerce revenue is carried as `custom_properties.revenue` (number) and
  `custom_properties.currency` (string, ISO-4217). The batch writer promotes
  these two keys into the dedicated ClickHouse `revenue` / `currency` columns.

---

## 7. Timing constants (unified)

These values were specified inconsistently across documents; they are frozen
here and referenced everywhere else.

| Constant | Value | Was inconsistent in |
|----------|-------|---------------------|
| Session inactivity timeout | **30 minutes** | — (consistent) |
| Engagement heartbeat interval | **10 seconds** | `clickhouse.md` said 5 s |
| Rage-click window | **1000 ms** (≥ 3 clicks, same target) | `clickhouse.md`/CH comments said 600 ms |
| Batch flush interval (tracker default) | **5 seconds** | distinct from the heartbeat interval — this is transport, not engagement |
| Scroll-depth thresholds (default) | 25, 50, 75, 90, 100 % | — |

---

## 8. `conversion` events

The tracker has no concept of a conversion. When an event is ingested, the
analytics layer evaluates it against the site's goal definitions
(`postgres.goals`):

- A `pageview` whose `pathname` matches a goal's `url_pattern`, or
- A `custom` event whose name matches a goal's `event_name`,

causes a derived `conversion` event to be recorded for rollup purposes
(`source_stats.conversions`, `conversion_rate`). Goal definition and the CRUD
API for goals are a Jalon-2 deliverable; see `feature-roadmap.md 2.7–2.10`.

---

## 9. `seq` and ordered analysis

`seq` is a monotonic per-session counter generated by the tracker
(`core/ids.ts`). It is a wire field, a canonical field, and a ClickHouse column.
It is the basis for reconstructing ordered event streams for funnel and path
analysis. Storing it (rather than relying on `timestamp` alone) disambiguates
events that share a millisecond.

---

## 10. Versioning

- **Additive** changes (new optional `custom_properties` keys, new optional
  behavioral signals) require no version bump.
- **Breaking** changes (rename, type change, removed required field) bump the
  event name with a `_vN` suffix; both versions are accepted for a 90-day
  window.
- `tracker_version` lets the ingestion layer apply migration shims for old
  client payloads.
- The wire ↔ canonical mapping may change freely as long as this table and the
  ingestion normaliser change together; that is not an API break because the
  canonical/API surface is unaffected.
