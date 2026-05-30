# ClickHouse Analytical Schema — Statflow

> Reference schema: `docs/data-model/clickhouse-schema.sql`

---

## 1. Design Principles

Statflow's ClickHouse layer is built around four constraints:

1. **Write-heavy, read-mostly** — events arrive in high-throughput bursts from the Redis Streams buffer; reads are dashboard queries that must return in under 200 ms at the p99.
2. **Privacy by design** — no raw IP addresses, no cookies, no join keys that link back to PII. `visitor_id` and `session_id` are derived hashes (see `identity-and-privacy.md`).
3. **Self-hostable** — the schema targets a single-node ClickHouse deployment and scales to a replicated cluster without structural changes (add `ReplicatedMergeTree` engines and a `Distributed` layer).
4. **Cost-conscious** — aggressive compression, short-TTL heatmap tables, and pre-aggregated rollups minimise storage and query CPU.

---

## 2. Table Inventory

| Table / View | Engine | Purpose |
|---|---|---|
| `events` | MergeTree | Canonical raw event store |
| `sessions` | ReplacingMergeTree | One row per session, incrementally built |
| `sessions_mv` | Materialized View → sessions | Triggers on every INSERT to events |
| `daily_stats` | AggregatingMergeTree | Per-site daily KPI rollup |
| `daily_stats_mv` | Materialized View → daily_stats | — |
| `page_stats` | AggregatingMergeTree | Per-page daily rollup |
| `page_stats_mv` | Materialized View → page_stats | — |
| `source_stats` | AggregatingMergeTree | Per-source (UTM + referrer) daily rollup |
| `source_stats_mv` | Materialized View → source_stats | — |
| `heatmap_stats` | SummingMergeTree | Pre-bucketed click coordinates for heatmaps |
| `heatmap_stats_mv` | Materialized View → heatmap_stats | — |
| `scroll_depth_stats` | AggregatingMergeTree | Scroll depth band distribution per page |
| `scroll_depth_stats_mv` | Materialized View → scroll_depth_stats | — |
| `funnel_events` | MergeTree | Sparse copy of goal-relevant events for funnel queries |
| `retention_cohorts` | ReplacingMergeTree | Weekly new-visitor cohorts + retention curve |

---

## 3. Canonical `events` Table

### 3.1 Schema Decisions

**`PARTITION BY toYYYYMM(timestamp)`**
Monthly partitions serve two purposes: (a) scans for date-range queries skip irrelevant months entirely, (b) GDPR "right to erasure" can be implemented as `ALTER TABLE events DROP PARTITION 'YYYYMM'` without a rewrite of the full table. For very large deployments a weekly `toYYYYMMDD(timestamp)` partition can reduce merge pressure at the cost of more parts.

**`ORDER BY (site_id, toStartOfHour(timestamp), event_name, visitor_id)`**
The primary sort key is chosen for the most common dashboard query shape: "all events for site X in time range Y, broken down by event_name." Bucketing timestamp to the hour keeps the key deterministic while reducing cardinality. `visitor_id` at the end gives per-visitor sequential scans without blowing up the key.

**`PRIMARY KEY (site_id, toStartOfHour(timestamp), event_name)`**
Narrower than ORDER BY so the sparse primary index covers common lookups, while MergeTree still sorts by visitor_id within each granule for better locality.

**`LowCardinality(String)` vs `String`**
Columns with bounded vocabularies (device_type, browser, os, country_code, referrer_source, utm_source, utm_medium) use `LowCardinality`. ClickHouse stores these as dictionary-encoded integers, reducing storage by 3–5x and enabling vectorised SIMD comparisons. Columns with unbounded cardinality (city, pathname, utm_campaign) remain plain `String` to avoid dictionary overflow.

**`Nullable(...)` for behavioural columns**
Click coordinates, scroll depth, and engagement time are NULL for non-behavioural events (e.g., pageviews). Using Nullable avoids sentinel values (e.g., 0 for click_x when there was no click) that would corrupt heatmap aggregations. The cost is a small additional bitmap column per Nullable field; acceptable given that most events are pageviews.

**Bloom-filter skip indexes**
Added on `pathname` and `visitor_id`. These allow ClickHouse to skip granules that cannot possibly contain a matching value, which reduces I/O on point-lookup queries ("all events for visitor X on page /pricing") by an order of magnitude on large tables.

**Projection `proj_by_page`**
Stores a pre-sorted sub-table by `(site_id, pathname, day)` inside the same MergeTree part. Queries on `GROUP BY pathname` hit this projection and skip the full events sort order.

### 3.2 Compression

ClickHouse defaults to LZ4; no explicit codec annotations are added to the canonical schema so that operators can tune per deployment. Recommended overrides for high-volume sites:

```sql
-- Apply after the fact via ALTER TABLE … MODIFY COLUMN
ALTER TABLE statflow.events
    MODIFY COLUMN visitor_id   String    CODEC(ZSTD(3)),
    MODIFY COLUMN session_id   String    CODEC(ZSTD(3)),
    MODIFY COLUMN pathname     String    CODEC(ZSTD(3)),
    MODIFY COLUMN properties   Map(String,String) CODEC(ZSTD(6));
```

`event_name`, `device_type`, and `browser` are already highly compressible as LowCardinality; no additional codec needed.

### 3.3 TTL and Retention

Raw-event retention is configured **in days** (range 30–730, default 365 — one unit, one range, one default across `SiteSettings.data_retention_days`, the `sites.retention_days` column, and this layer). The `events` table carries a backstop table-level TTL of **730 days** (the platform maximum). The per-site value is enforced by the **retention worker**, a scheduled job that issues, per site:

```sql
-- Delete a site's events older than its configured window (non-blocking).
ALTER TABLE statflow.events
    DELETE WHERE site_id = {site_id:UUID}
      AND timestamp < now() - INTERVAL {retention_days:UInt16} DAY;
```

When a whole month is past every site's window the worker uses `DROP PARTITION` instead. Aggregated rollup tables retain data for 24 months. Heatmap data (`heatmap_stats`) has a shorter 12-month TTL because heatmaps become analytically irrelevant after a site redesign.

---

## 4. Session Construction

Sessions cannot be derived atomically at INSERT time because a session only "closes" after a 30-minute idle gap or an explicit session_end event. The architecture handles this in two layers:

1. **Materialized view `sessions_mv`** aggregates partial session state on each INSERT block using `ReplacingMergeTree`. The view emits an incomplete session row (with `ended_at = max(timestamp)` of the current block) that is overwritten by subsequent blocks via `ReplacingMergeTree`'s deduplication.

2. **Session finalizer** (a background worker in the ingestion service) monitors Redis Streams for visitor inactivity. After 30 minutes without an event for a given `session_id`, it emits a synthetic `session_end` event into ClickHouse. This triggers a final MV update with accurate `ended_at`, `duration_s`, `exit_page`, and `bounce` values.

This avoids expensive `FINAL` clauses on every session query in exchange for a well-understood eventual-consistency window (max ~30 min lag for session duration accuracy).

---

## 5. Behavioural Events Storage

### 5.1 Clicks and Heatmaps

Every `click` event captures:

- `click_x` / `click_y` — pointer position **relative to the document origin** (`clientX + scrollX`), CSS pixels, so the coordinate stays valid after scroll. One coordinate origin is used end to end (tracker, API, ClickHouse) — see `event-contract.md §5.1`.
- `click_x_pct` / `click_y_pct` — the same position as a percentage of document width/height. Resolution-independent; this is the representation the `heatmap_stats` grid is built from. (There are no element-relative `click_tx`/`click_ty` columns — no tracker or API field ever produced them.)
- `viewport_width` / `viewport_height` — captured at click time. The viewport may differ from `screen_width`/`screen_height` (e.g., split-screen, mobile with browser chrome).
- `is_rage_click` — set by the tracker SDK when ≥ 3 clicks occur within 1000 ms on the same target (the canonical rage-click window — `event-contract.md §7`). The backend validates this flag before storage.
- `element_selector` — a CSS selector generated by the SDK (e.g., `#cta-hero > button.btn-primary`). Limited to 512 characters and stripped of any attribute values that could leak PII (e.g., `input[value=...]` is normalised to `input`).
- `element_text` — visible text content of the target element, truncated at 128 characters. Sanitised server-side to remove email-like patterns.

For rendering, the `heatmap_stats` pre-aggregate buckets the **percentage** coordinates (`click_x_pct` / `click_y_pct`) into a resolution-independent 200 × 200 cell grid. Building the grid from percentages — rather than normalising document-relative pixels against a viewport width — is correct for any viewport and survives scroll. The raw `events` table retains the original pixel coordinates for custom-resolution queries.

### 5.2 Scroll Depth

`scroll_depth` events are fired by the SDK at the configured thresholds (default 25%, 50%, 75%, 90%, 100%), plus on page unload with the current depth. Both `scroll_depth_px` (absolute) and `scroll_depth_pct` (percentage of total page height) are stored. The percentage variant feeds the `scroll_depth_stats` rollup (whose MV filters `event_name = 'scroll_depth'`); the pixel variant is preserved for per-page absolute analysis.

### 5.3 Engagement Time

`engagement_time_ms` is accumulated by the tracker using the Page Visibility API and pointer activity events. It measures "active" time (tab in foreground, user interacting) rather than raw time-on-page. This aligns with GA4's definition. The value is sent on each `engagement` heartbeat (every **10 seconds** of active engagement — the canonical interval in `event-contract.md §7`) and on page unload. The 5-second figure in earlier drafts referred to the *batch flush* interval, which is a separate transport setting.

### 5.4 Rage Clicks

A rage-click burst (≥ 3 clicks on the same target within 1000 ms) is emitted by the tracker as a dedicated `rage_click` event carrying the same behavioral signals as a `click`, with `is_rage_click = 1`. Rage-click events are stored in the same `events` table (not a separate table). The `heatmap_stats` MV aggregates `event_name IN ('click','rage_click')` and counts the rage subset into a separate `rage_click_count` column, so the heatmap UI can toggle between "all clicks" and "rage clicks only" layers without additional queries.

---

## 6. Rollup Query Patterns

### Unique visitor counts

All `unique_visitors` columns in rollup tables use `AggregateFunction(uniq, String)`, storing a HyperLogLog sketch. Final counts use `uniqMerge()`:

```sql
SELECT uniqMerge(unique_visitors) AS uv
FROM statflow.daily_stats
WHERE site_id = {site_id:UUID}
  AND day BETWEEN {start:Date} AND {end:Date};
```

HLL has ±0.8% error at 95% confidence — acceptable for analytics dashboards. For exact counts on small date ranges, query `events` directly.

Additive rollup columns (`pageviews`, `events`, `total_engagement_ms`,
`total_revenue`, and `source_stats.conversions`) are `SimpleAggregateFunction(sum, …)`
and are read back with a plain `sum()`. `unique_visitors` / `unique_sessions`
(and `source_stats.sessions`) are `AggregateFunction(uniq, …)` and are read back
with `uniqMerge()`.

### Bounce rate

Bounce rate is **computed at query time from the `sessions` table** — the rollup
tables carry no `bounces` / `bounce_sessions` column, because a single INSERT
block cannot observe a whole session. A bounce is a session with one pageview:

```sql
SELECT
    toDate(started_at)                       AS day,
    countIf(bounce = 1) * 100.0 / count()    AS bounce_rate
FROM statflow.sessions FINAL
WHERE site_id = {site_id:UUID}
  AND started_at >= {start:DateTime}
  AND started_at <  {end:DateTime}
GROUP BY day
ORDER BY day;
```

`FINAL` is safe on `sessions` here because this is a low-frequency reporting query, not a real-time widget.

---

## 7. Realtime (Last 5 Minutes)

The realtime window is **5 minutes** platform-wide — one value across the API
(`GET /analytics/{site_id}/realtime` and `.../realtime/stream`), the roadmap,
and this layer. Realtime data is served from two tiers:

1. **Redis** — the ingestion service increments per-site Redis counters (`INCR statflow:rt:{site_id}:visitors`) with a 5-minute sliding expiry on each key. These are the primary source for live visitor widgets (sub-10 ms reads).

2. **ClickHouse fallback** — the analytics API falls back to a direct `events` scan for the last 5 minutes when Redis counters are unavailable or when richer breakdown (by page, country) is needed:

```sql
SELECT
    uniq(visitor_id)       AS active_visitors,
    uniq(session_id)       AS active_sessions,
    topK(10)(pathname)     AS top_pages
FROM statflow.events
WHERE site_id = {site_id:UUID}
  AND timestamp >= now64(3) - INTERVAL 5 MINUTE;
```

With the table's ORDER BY and a 5-minute window, this query scans at most 1–2 parts on a well-loaded table. The dashboard's Realtime screen consumes this via the SSE endpoint `GET /api/v1/analytics/{site_id}/realtime/stream`.

---

## 8. Funnel Analysis

Funnel queries operate on `funnel_events`, a sparse copy of events filtered to goal-relevant event names. The funnel query engine (in the analytics service) uses a window-function approach:

```sql
-- Simplified: ordered steps within a session
SELECT
    session_id,
    groupArray(step_index) AS steps_completed
FROM statflow.funnel_events
WHERE site_id = {site_id:UUID}
  AND funnel_id = {funnel_id:UUID}
  AND timestamp BETWEEN {start} AND {end}
GROUP BY session_id
HAVING has(steps_completed, 0) -- must start at step 0
ORDER BY session_id;
```

Writing only goal-relevant events to `funnel_events` (filtered at ingestion via the site's funnel configuration stored in PostgreSQL) keeps this table 50–100x smaller than `events` and allows sub-second funnel queries without approximation.

---

## 9. Scaling Considerations

| Concern | Single-node approach | Cluster approach |
|---|---|---|
| Write throughput | Redis Streams batching + async INSERT | `Distributed` table over sharded MergeTree |
| Read scaling | Materialized views absorb 90% of dashboard load | Replicas for read-only workloads |
| Storage | TTL + monthly DROP PARTITION | Same, plus S3/GCS tiered storage via `storage_policy` |
| HA | Not applicable | ZooKeeper/ClickHouse Keeper + ReplicatedMergeTree |

Migration from single-node to cluster requires only renaming engines from `MergeTree` to `ReplicatedMergeTree` and adding a `Distributed` umbrella; the schema structure is unchanged.

---

## 10. Open Questions

1. **Session boundary in MVs** — `sessions_mv` cannot detect a 30-minute gap because MVs are evaluated per-block, not across time. The session finalizer background worker is necessary but adds operational complexity. An alternative is to compute sessions entirely in the ingestion layer (stateful stream processing via Redis sorted sets) and write a single complete session row directly to `sessions`. This removes the MV entirely but requires careful handling of late-arriving events.

2. **`FINAL` vs. `GROUP BY` on ReplacingMergeTree** — `SELECT … FROM sessions FINAL` forces a synchronous merge and blocks during large scans. For high-traffic tables, querying with `GROUP BY session_id, argMax(version)` is faster but more verbose. The reporting layer should abstract this behind a view.

3. **Funnel event fan-out** — **Resolved (ADR-0007, B11).** Funnels are
   first-class persisted resources (`postgres.funnels` / `funnel_steps` and the
   funnels CRUD API). When a funnel is created or updated, the ingestion service
   learns its step definitions and writes matching events into `funnel_events`;
   a nightly `INSERT INTO funnel_events SELECT … FROM events WHERE …` job
   backfills events ingested before the funnel existed. The accepted cost is a
   bounded nightly write-amplification spike. The ad-hoc-funnels alternative is
   not pursued — it is incompatible with ingestion-time fan-out and with the
   persisted-funnel screens.

4. **Heatmap resolution** — **Resolved (B12).** The heatmap grid is built from
   the resolution-independent percentage coordinates (`click_x_pct` /
   `click_y_pct`) at a fixed 200 × 200 cell resolution (0.5%-wide cells), which
   is correct for every viewport width including mobile. No device-specific
   pixel bucket size is needed.

5. **Revenue aggregation precision** — `Decimal(38, 4)` in rollup tables can overflow with extremely high-volume e-commerce sites. Monitor the rollup total_revenue column; if values approach `10^34`, switch to `Float64` (lossy but sufficient for analytics).
