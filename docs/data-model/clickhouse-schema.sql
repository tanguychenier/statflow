-- =============================================================================
-- Statflow — ClickHouse Analytical Schema
-- Engine: ClickHouse 24.x+
-- Retention: the table-level TTL is 730 days (the platform maximum). Per-site
--   retention is configured in days (range 30-730, default 365) on
--   postgres.sites.retention_days and SiteSettings.data_retention_days, and is
--   enforced by the retention worker, which runs a scheduled, per-site
--   `ALTER TABLE statflow.events DELETE WHERE site_id = ... AND timestamp < ...`
--   (or DROP PARTITION when a whole month is past every site's window). The
--   table TTL is the backstop; the worker is the per-site override mechanism.
-- All timestamps stored in UTC.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 0. DATABASE
-- ---------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS statflow;
USE statflow;

-- ---------------------------------------------------------------------------
-- 1. CANONICAL EVENTS TABLE
-- ---------------------------------------------------------------------------
-- Primary landing table for every collected event.
-- Written by the ingestion service via Redis Streams → batch INSERT.
-- Partitioned by month to allow cheap DROP PARTITION for GDPR erasure.
-- ORDER BY puts site_id first so all per-site queries hit minimal parts.
-- visitor_id / session_id are salted hashes — see docs/data-model/identity-and-privacy.md.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.events
(
    -- Core identifiers
    site_id             UUID,
    event_id            UUID,                        -- client-generated; idempotency key
    -- event_name vocabulary is the closed set in docs/data-model/event-contract.md §4:
    -- 'pageview','route_change','engagement','click','rage_click','dead_click',
    -- 'scroll_depth','form_focus','form_submit','form_abandon','element_visibility',
    -- 'custom','conversion','web_vital_lcp','web_vital_cls','web_vital_inp',
    -- 'js_error','heatmap_batch'
    event_name          LowCardinality(String),
    timestamp           DateTime64(3, 'UTC'),
    seq                 UInt32,                      -- monotonic per-session counter
    tracker_version     LowCardinality(String),
    visitor_id          String,                      -- 64-char hex, daily-salted HMAC (ADR-0008)
    session_id          String,                      -- 64-char hex, same daily salt + 'session' domain tag

    -- Page context
    hostname            String,
    pathname            String,
    referrer            String,
    referrer_source     LowCardinality(String),      -- 'google','twitter','direct'…

    -- UTM parameters
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),
    utm_campaign        String,
    utm_term            String,
    utm_content         String,

    -- Geo (resolved at ingestion, not stored raw IP)
    country_code        LowCardinality(FixedString(2)),
    region              LowCardinality(String),
    city                String,

    -- Device / browser fingerprint (no PII)
    device_type         LowCardinality(String),      -- 'desktop','mobile','tablet'
    browser             LowCardinality(String),
    browser_version     LowCardinality(String),
    os                  LowCardinality(String),
    os_version          LowCardinality(String),
    screen_width        UInt16,
    screen_height       UInt16,
    language            LowCardinality(String),

    -- Behavioral payload (populated for interaction events)
    -- click_x / click_y: pointer coords DOCUMENT-relative (clientX+scrollX), CSS px.
    --   One coordinate origin is used end to end (see event-contract.md §5.1).
    --   Element-relative coordinates are not collected; the click_tx/click_ty
    --   columns were removed because no tracker or API field ever produced them.
    -- viewport_width / viewport_height: at time of interaction (may differ from screen_*)
    -- scroll_depth_px: absolute vertical scroll offset in pixels
    -- scroll_depth_pct: percentage of page height scrolled (0-100)
    -- engagement_time_ms: milliseconds of active engagement since last heartbeat
    -- is_rage_click: >=3 clicks within 1000 ms on the same target (event-contract.md §7)
    -- element_selector: CSS selector of the interacted element (max 512 chars, sanitised)
    -- element_text: visible text of target element (truncated at 128 chars, sanitised)
    click_x             Nullable(UInt32),
    click_y             Nullable(UInt32),
    -- Percentage coordinates (0-100, one decimal) — the stable cross-resolution
    -- representation; basis for the heatmap grid (event-contract.md §5.1).
    click_x_pct         Nullable(Float32),
    click_y_pct         Nullable(Float32),
    viewport_width      Nullable(UInt16),
    viewport_height     Nullable(UInt16),
    scroll_depth_px     Nullable(UInt32),
    scroll_depth_pct    Nullable(UInt8),
    engagement_time_ms  Nullable(UInt32),
    is_rage_click       UInt8 DEFAULT 0,             -- 0/1 boolean
    element_selector    Nullable(String),
    element_text        Nullable(String),

    -- Custom event properties (freeform key/value, max ~50 keys per event enforced at ingestion)
    properties          Map(String, String),

    -- E-commerce
    revenue             Nullable(Decimal(18, 4)),
    currency            LowCardinality(String),

    -- Internal
    ingested_at         DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = MergeTree
PARTITION BY toYYYYMM(timestamp)
ORDER BY (site_id, toStartOfHour(timestamp), event_name, visitor_id)
-- Primary key narrower than ORDER BY for faster lookups on hot queries
PRIMARY KEY (site_id, toStartOfHour(timestamp), event_name)
-- Backstop TTL = 730 days (platform maximum). Per-site retention (30-730 days,
-- default 365) is enforced by the retention worker — see the file header.
TTL toDateTime(timestamp) + INTERVAL 730 DAY
SETTINGS
    index_granularity        = 8192,
    min_bytes_for_wide_part  = 104857600,   -- 100 MB: small parts stay Compact
    merge_with_ttl_timeout   = 86400;       -- TTL merge at most once per day

-- Bloom-filter skip index on pathname for queries like "all events on /pricing"
ALTER TABLE statflow.events
    ADD INDEX idx_pathname pathname TYPE bloom_filter(0.01) GRANULARITY 4;

-- Bloom-filter skip index on visitor_id for per-visitor lookups
ALTER TABLE statflow.events
    ADD INDEX idx_visitor_id visitor_id TYPE bloom_filter(0.01) GRANULARITY 4;

-- ---------------------------------------------------------------------------
-- 2. PROJECTION: fast per-page aggregates inside the events table
-- Stored as a hidden sub-table sorted by (site_id, pathname, timestamp).
-- Eliminates full scans for the "top pages" widget.
-- ---------------------------------------------------------------------------
ALTER TABLE statflow.events
    ADD PROJECTION proj_by_page
    (
        SELECT
            site_id,
            pathname,
            toStartOfDay(timestamp) AS day,
            count()                 AS hits,
            uniq(visitor_id)        AS unique_visitors,
            uniq(session_id)        AS sessions,
            avg(engagement_time_ms) AS avg_engagement_ms
        GROUP BY site_id, pathname, toStartOfDay(timestamp)
    );

ALTER TABLE statflow.events MATERIALIZE PROJECTION proj_by_page;

-- ---------------------------------------------------------------------------
-- 3. MATERIALIZED VIEW: sessions_mv  →  sessions
-- ---------------------------------------------------------------------------
-- One row per session; computed from raw events on INSERT.
-- Session = uninterrupted activity window (defined as: gap > 30 min resets session).
-- Because ClickHouse MVs fire per INSERT block, we aggregate at session_id grain
-- and rely on ReplacingMergeTree to reconcile duplicates during background merges.
-- The ingestion layer must finalize session rows after the 30-min idle timeout and
-- re-insert a "session_end" synthetic event so the MV can capture final duration.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.sessions
(
    site_id             UUID,
    session_id          String,
    visitor_id          String,

    -- Timing
    started_at          DateTime64(3, 'UTC'),
    ended_at            DateTime64(3, 'UTC'),
    duration_s          UInt32,              -- ended_at - started_at in seconds

    -- Entry / exit
    entry_page          String,
    exit_page           String,
    referrer            String,
    referrer_source     LowCardinality(String),
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),
    utm_campaign        String,

    -- Geo / device (from the first event in session)
    country_code        LowCardinality(FixedString(2)),
    region              LowCardinality(String),
    city                String,
    device_type         LowCardinality(String),
    browser             LowCardinality(String),
    os                  LowCardinality(String),
    language            LowCardinality(String),

    -- Metrics
    pageview_count      UInt16,
    event_count         UInt16,
    bounce              UInt8,               -- 1 if pageview_count = 1
    total_engagement_ms UInt32,

    -- Version for ReplacingMergeTree (ingested_at of last contributing block)
    version             DateTime64(3, 'UTC')
)
ENGINE = ReplacingMergeTree(version)
PARTITION BY toYYYYMM(started_at)
ORDER BY (site_id, session_id)
TTL toDateTime(started_at) + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.sessions_mv
TO statflow.sessions
AS
SELECT
    site_id,
    session_id,
    any(visitor_id)                         AS visitor_id,
    min(timestamp)                          AS started_at,
    max(timestamp)                          AS ended_at,
    toUInt32(
        dateDiff('second', min(timestamp), max(timestamp))
    )                                       AS duration_s,
    argMin(pathname, timestamp)             AS entry_page,
    argMax(pathname, timestamp)             AS exit_page,
    anyLast(referrer)                       AS referrer,
    anyLast(referrer_source)                AS referrer_source,
    anyLast(utm_source)                     AS utm_source,
    anyLast(utm_medium)                     AS utm_medium,
    anyLast(utm_campaign)                   AS utm_campaign,
    anyLast(country_code)                   AS country_code,
    anyLast(region)                         AS region,
    anyLast(city)                           AS city,
    anyLast(device_type)                    AS device_type,
    anyLast(browser)                        AS browser,
    anyLast(os)                             AS os,
    anyLast(language)                       AS language,
    countIf(event_name = 'pageview')        AS pageview_count,
    count()                                 AS event_count,
    toUInt8(countIf(event_name = 'pageview') <= 1) AS bounce,
    toUInt32(sumIf(engagement_time_ms,
        engagement_time_ms IS NOT NULL))    AS total_engagement_ms,
    max(ingested_at)                        AS version
FROM statflow.events
GROUP BY site_id, session_id;

-- ---------------------------------------------------------------------------
-- 4. MATERIALIZED VIEW: daily_stats_mv  →  daily_stats
-- ---------------------------------------------------------------------------
-- Pre-aggregated daily rollup per site, used by the standard dashboard.
-- ENGINE is AggregatingMergeTree. Additive columns therefore MUST be either
-- SimpleAggregateFunction(sum, ...) or AggregateFunction(...): plain UInt/Decimal
-- columns are NOT summed on merge under AggregatingMergeTree. Approximate unique
-- counts use AggregateFunction(uniq, ...) and are read back with uniqMerge().
--
-- `bounces` is fed from the events stream directly: a bounce is a session with a
-- single pageview. The MV cannot see the whole session, so per-INSERT-block it
-- contributes a uniq-state of session_ids that, in this block, look like a bounce
-- candidate; the authoritative bounce rate is computed at query time from the
-- `sessions` table (see clickhouse.md §6). The `bounce_pageviews` column below is
-- the cheap dashboard approximation: pageviews attributed to single-pageview
-- sessions within the block. For exact bounce rate, join `sessions`.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.daily_stats
(
    site_id             UUID,
    day                 Date,
    country_code        LowCardinality(FixedString(2)),
    device_type         LowCardinality(String),
    referrer_source     LowCardinality(String),
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),

    -- Additive metrics — SimpleAggregateFunction(sum) so AggregatingMergeTree merges them
    pageviews           SimpleAggregateFunction(sum, UInt64),
    events              SimpleAggregateFunction(sum, UInt64),
    total_engagement_ms SimpleAggregateFunction(sum, UInt64),
    total_revenue       SimpleAggregateFunction(sum, Decimal(38, 4)),

    -- Approximate unique counts via HLL sketches
    unique_visitors     AggregateFunction(uniq, String),
    unique_sessions     AggregateFunction(uniq, String)
)
ENGINE = AggregatingMergeTree
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, country_code, device_type, referrer_source, utm_source, utm_medium)
TTL day + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.daily_stats_mv
TO statflow.daily_stats
AS
SELECT
    site_id,
    toDate(timestamp)                           AS day,
    country_code,
    device_type,
    referrer_source,
    utm_source,
    utm_medium,
    countIf(event_name = 'pageview')            AS pageviews,
    count()                                     AS events,
    toUInt64(sumIf(engagement_time_ms,
        engagement_time_ms IS NOT NULL))        AS total_engagement_ms,
    sumIf(revenue, revenue IS NOT NULL)         AS total_revenue,
    uniqState(visitor_id)                       AS unique_visitors,
    uniqState(session_id)                       AS unique_sessions
FROM statflow.events
GROUP BY
    site_id, toDate(timestamp), country_code, device_type, referrer_source, utm_source, utm_medium;

-- ---------------------------------------------------------------------------
-- 5. MATERIALIZED VIEW: page_stats_mv  →  page_stats
-- ---------------------------------------------------------------------------
-- Per-page daily rollup; backs "Top pages" and "Content analytics" reports.
-- ---------------------------------------------------------------------------
-- Additive columns use SimpleAggregateFunction(sum); unique counts and the
-- scroll-depth average use AggregateFunction. Bounce is computed at query time
-- from `sessions` (entry_page join), so page_stats carries no bounce column.
CREATE TABLE IF NOT EXISTS statflow.page_stats
(
    site_id             UUID,
    day                 Date,
    hostname            String,
    pathname            String,

    pageviews           SimpleAggregateFunction(sum, UInt64),
    total_engagement_ms SimpleAggregateFunction(sum, UInt64),
    unique_visitors     AggregateFunction(uniq, String),
    unique_sessions     AggregateFunction(uniq, String),
    scroll_depth_pct_avg AggregateFunction(avg, UInt8)
)
ENGINE = AggregatingMergeTree
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, hostname, pathname)
TTL day + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.page_stats_mv
TO statflow.page_stats
AS
SELECT
    site_id,
    toDate(timestamp)                           AS day,
    hostname,
    pathname,
    countIf(event_name = 'pageview')            AS pageviews,
    toUInt64(sumIf(engagement_time_ms,
        engagement_time_ms IS NOT NULL))        AS total_engagement_ms,
    uniqState(visitor_id)                       AS unique_visitors,
    uniqState(session_id)                       AS unique_sessions,
    avgStateIf(scroll_depth_pct,
        scroll_depth_pct IS NOT NULL)           AS scroll_depth_pct_avg
FROM statflow.events
GROUP BY site_id, toDate(timestamp), hostname, pathname;

-- ---------------------------------------------------------------------------
-- 6. MATERIALIZED VIEW: source_stats_mv  →  source_stats
-- ---------------------------------------------------------------------------
-- Per-source (referrer + UTM) daily rollup; backs acquisition reports.
-- ---------------------------------------------------------------------------
-- Additive columns use SimpleAggregateFunction(sum); unique counts (sessions and
-- visitors) use AggregateFunction(uniq) — read back with uniqMerge(). Mixing a raw
-- uniq() with a uniqState() in the same AggregatingMergeTree MV would corrupt the
-- raw column on merge, so both unique columns are stateful.
CREATE TABLE IF NOT EXISTS statflow.source_stats
(
    site_id             UUID,
    day                 Date,
    referrer_source     LowCardinality(String),
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),
    utm_campaign        String,

    sessions            AggregateFunction(uniq, String),
    unique_visitors     AggregateFunction(uniq, String),
    pageviews           SimpleAggregateFunction(sum, UInt64),
    total_revenue       SimpleAggregateFunction(sum, Decimal(38, 4)),
    conversions         SimpleAggregateFunction(sum, UInt64)
)
ENGINE = AggregatingMergeTree
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, referrer_source, utm_source, utm_medium, utm_campaign)
TTL day + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.source_stats_mv
TO statflow.source_stats
AS
SELECT
    site_id,
    toDate(timestamp)                           AS day,
    referrer_source,
    utm_source,
    utm_medium,
    utm_campaign,
    uniqState(session_id)                       AS sessions,
    uniqState(visitor_id)                       AS unique_visitors,
    countIf(event_name = 'pageview')            AS pageviews,
    sumIf(revenue, revenue IS NOT NULL)         AS total_revenue,
    countIf(event_name = 'conversion')          AS conversions
FROM statflow.events
GROUP BY site_id, toDate(timestamp), referrer_source, utm_source, utm_medium, utm_campaign;

-- ---------------------------------------------------------------------------
-- 7. REALTIME VIEW: last 5 minutes  (no MV — query hits events directly)
-- ---------------------------------------------------------------------------
-- The realtime window is 5 minutes platform-wide (canonical decision: matches
-- the OpenAPI /realtime + /realtime/stream endpoints, the roadmap, and the
-- Realtime screen's active-visitor counter). Not a stored table; the analytics
-- API executes this at query time. Provided here as a canonical reference query.
--
-- SELECT
--     site_id,
--     count()                    AS active_events,
--     uniq(visitor_id)           AS active_visitors,
--     uniq(session_id)           AS active_sessions,
--     topK(10)(pathname)         AS top_pages
-- FROM statflow.events
-- WHERE site_id = {site_id:UUID}
--   AND timestamp >= now64(3) - INTERVAL 5 MINUTE
-- GROUP BY site_id;
--
-- For live counters with sub-second freshness, the ingestion service can
-- maintain Redis counters in parallel (INCR / EXPIRE 5m) and the API reads
-- Redis directly, falling back to this ClickHouse query for full accuracy.
-- The SSE stream (GET /analytics/{site_id}/realtime/stream) is fed from the
-- same 5-minute window.

-- ---------------------------------------------------------------------------
-- 8. HEATMAP AGGREGATE: heatmap_stats
-- ---------------------------------------------------------------------------
-- Pre-buckets click coordinates into a grid for heatmap rendering.
-- Click coordinates are DOCUMENT-relative; the percentage coordinates
-- (click_x_pct / click_y_pct) are the resolution-independent representation,
-- so the grid is built from percentages. Grid resolution: a 200 x 200 cell
-- grid, i.e. 0.5%-wide cells (bucket = round(pct * 2)). This is correct for any
-- viewport width and survives scroll, unlike viewport-relative normalisation.
-- Allows the frontend to render heatmaps without scanning raw events.
--
-- Note: this MV aggregates discrete `click` / `rage_click` events. The Jalon-3
-- `heatmap_batch` mouse-move stream is handled by a separate Jalon-3 pipeline
-- (see event-contract.md §4) and does not feed this table.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.heatmap_stats
(
    site_id             UUID,
    day                 Date,
    pathname            String,
    device_type         LowCardinality(String),
    -- 0..200 grid cell index (percentage * 2), resolution-independent
    bucket_x            UInt16,
    bucket_y            UInt16,
    click_count         UInt64,
    rage_click_count    UInt64
)
ENGINE = SummingMergeTree((click_count, rage_click_count))
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, pathname, device_type, bucket_x, bucket_y)
TTL day + INTERVAL 12 MONTH   -- shorter TTL: heatmaps are less useful after 12 months
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.heatmap_stats_mv
TO statflow.heatmap_stats
AS
SELECT
    site_id,
    toDate(timestamp)                               AS day,
    pathname,
    device_type,
    toUInt16(round(ifNull(click_x_pct, 0) * 2))     AS bucket_x,
    toUInt16(round(ifNull(click_y_pct, 0) * 2))     AS bucket_y,
    count()                                         AS click_count,
    countIf(is_rage_click = 1)                      AS rage_click_count
FROM statflow.events
WHERE event_name IN ('click', 'rage_click')
    AND click_x_pct IS NOT NULL
    AND click_y_pct IS NOT NULL
GROUP BY
    site_id, toDate(timestamp), pathname, device_type,
    toUInt16(round(ifNull(click_x_pct, 0) * 2)),
    toUInt16(round(ifNull(click_y_pct, 0) * 2));

-- ---------------------------------------------------------------------------
-- 9. SCROLL DEPTH AGGREGATE: scroll_depth_stats
-- ---------------------------------------------------------------------------
-- Buckets scroll depth into 10% bands (0-10, 10-20, … 90-100).
-- Backs the "scroll depth funnel" visualisation.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.scroll_depth_stats
(
    site_id             UUID,
    day                 Date,
    pathname            String,
    device_type         LowCardinality(String),
    depth_band          UInt8,   -- 0,10,20,…,90 (lower bound of 10% band)
    visitor_count       AggregateFunction(uniq, String)
)
ENGINE = AggregatingMergeTree
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, pathname, device_type, depth_band)
TTL day + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS statflow.scroll_depth_stats_mv
TO statflow.scroll_depth_stats
AS
SELECT
    site_id,
    toDate(timestamp)                               AS day,
    pathname,
    device_type,
    toUInt8(
        floor(ifNull(scroll_depth_pct, 0) / 10) * 10
    )                                               AS depth_band,
    uniqState(visitor_id)                           AS visitor_count
FROM statflow.events
-- event name is 'scroll_depth' (event-contract.md §4); the earlier 'scroll'
-- filter captured zero rows because the tracker never emits 'scroll'.
WHERE event_name = 'scroll_depth'
    AND scroll_depth_pct IS NOT NULL
GROUP BY
    site_id, toDate(timestamp), pathname, device_type,
    toUInt8(floor(ifNull(scroll_depth_pct, 0) / 10) * 10);

-- ---------------------------------------------------------------------------
-- 10. FUNNEL EVENTS: funnel_events (sparse, event-level copy for funnel queries)
-- ---------------------------------------------------------------------------
-- Funnels are first-class persisted resources (postgres.funnels / funnel_steps,
-- and the funnels CRUD API). Funnels require ordered per-visitor/session event
-- streams; full-table scans on `events` handle these poorly at high cardinality.
--
-- This table is a lightweight projection: when a funnel is created or updated
-- via the API, the ingestion service learns its step definitions and writes a
-- matching row here for every qualifying event. A nightly backfill job
-- (INSERT INTO funnel_events SELECT ... FROM events WHERE ...) populates the
-- table for funnels created after the events were ingested — see clickhouse.md
-- §8 and ADR-0007. Ordering uses `seq` (then `timestamp`) so events sharing a
-- millisecond are still correctly sequenced.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.funnel_events
(
    site_id     UUID,
    funnel_id   UUID,        -- references postgres.funnels.id (advisory, not enforced)
    step_index  UInt8,
    session_id  String,
    visitor_id  String,
    event_id    UUID,
    seq         UInt32,
    timestamp   DateTime64(3, 'UTC'),
    pathname    String,
    event_name  LowCardinality(String),
    properties  Map(String, String)
)
ENGINE = MergeTree
PARTITION BY toYYYYMM(timestamp)
ORDER BY (site_id, funnel_id, session_id, step_index, seq, timestamp)
TTL toDateTime(timestamp) + INTERVAL 730 DAY
SETTINGS index_granularity = 8192;

-- ---------------------------------------------------------------------------
-- 11. RETENTION: retention_cohorts
-- ---------------------------------------------------------------------------
-- Weekly new-visitor cohorts with returning counts per week offset.
-- Written by a nightly batch job (not an MV) because computing "first seen"
-- requires a global aggregation that MVs cannot safely do incrementally.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.retention_cohorts
(
    site_id         UUID,
    cohort_week     Date,        -- Monday of the cohort's acquisition week
    week_offset     UInt8,       -- 0 = same week, 1 = 1 week later, …
    new_visitors    UInt64,      -- count in cohort (populated only at offset 0)
    retained        UInt64       -- count of cohort members seen in this offset week
)
ENGINE = ReplacingMergeTree
PARTITION BY toYYYYMM(cohort_week)
ORDER BY (site_id, cohort_week, week_offset)
TTL cohort_week + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;
