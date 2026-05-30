-- =============================================================================
-- Statflow — ClickHouse schema initialisation
--
-- Executed once at container first-start by the ClickHouse Docker entrypoint
-- when placed in /docker-entrypoint-initdb.d/.
--
-- This file is the deployable mirror of docs/data-model/clickhouse-schema.sql
-- (the normative reference). Keep the two in lockstep: the events table column
-- set is the contract the ingestion batch writer (ClickHouseRowMapper /
-- ClickHouseEventWriter, JSONEachRow) inserts against, and the materialized
-- views back the dashboard/realtime/heatmap reports.
--
-- Design notes:
--   * Partitioned by month (toYYYYMM) — cheap DROP PARTITION for GDPR erasure.
--   * ORDER BY (site_id, toStartOfHour(timestamp), event_name, visitor_id) keeps
--     per-site time-range scans on minimal parts.
--   * Table TTL is the 730-day backstop; the retention worker enforces the
--     per-site window (30-730 days, default 365).
--   * LowCardinality columns are dictionary-encoded enum-like strings.
--   * properties Map(String, String) stores per-event metadata without DDL.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS statflow;

-- ---------------------------------------------------------------------------
-- 1. CANONICAL EVENTS TABLE
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.events
(
    -- Core identifiers
    site_id             UUID,
    event_id            UUID,
    event_name          LowCardinality(String),
    timestamp           DateTime64(3, 'UTC'),
    seq                 UInt32,
    tracker_version     LowCardinality(String),
    visitor_id          String,
    session_id          String,

    -- Page context
    hostname            String,
    pathname            String,
    referrer            String,
    referrer_source     LowCardinality(String),

    -- UTM parameters
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),
    utm_campaign        String,
    utm_term            String,
    utm_content         String,

    -- Geo (resolved at ingestion; raw IP never stored)
    country_code        LowCardinality(FixedString(2)),
    region              LowCardinality(String),
    city                String,

    -- Device / browser (parsed server-side; raw UA never stored)
    device_type         LowCardinality(String),
    browser             LowCardinality(String),
    browser_version     LowCardinality(String),
    os                  LowCardinality(String),
    os_version          LowCardinality(String),
    screen_width        UInt16,
    screen_height       UInt16,
    language            LowCardinality(String),

    -- Behavioral payload (interaction events)
    click_x             Nullable(UInt32),
    click_y             Nullable(UInt32),
    click_x_pct         Nullable(Float32),
    click_y_pct         Nullable(Float32),
    viewport_width      Nullable(UInt16),
    viewport_height     Nullable(UInt16),
    scroll_depth_px     Nullable(UInt32),
    scroll_depth_pct    Nullable(UInt8),
    engagement_time_ms  Nullable(UInt32),
    is_rage_click       UInt8 DEFAULT 0,
    element_selector    Nullable(String),
    element_text        Nullable(String),

    -- Custom event properties
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
PRIMARY KEY (site_id, toStartOfHour(timestamp), event_name)
TTL toDateTime(timestamp) + INTERVAL 730 DAY
SETTINGS
    index_granularity        = 8192,
    min_bytes_for_wide_part  = 104857600,
    merge_with_ttl_timeout   = 86400;

ALTER TABLE statflow.events
    ADD INDEX IF NOT EXISTS idx_pathname pathname TYPE bloom_filter(0.01) GRANULARITY 4;

ALTER TABLE statflow.events
    ADD INDEX IF NOT EXISTS idx_visitor_id visitor_id TYPE bloom_filter(0.01) GRANULARITY 4;

-- ---------------------------------------------------------------------------
-- 2. PROJECTION: per-page aggregates inside the events table
-- ---------------------------------------------------------------------------
ALTER TABLE statflow.events
    ADD PROJECTION IF NOT EXISTS proj_by_page
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

-- ---------------------------------------------------------------------------
-- 3. SESSIONS rollup (sessions_mv → sessions)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.sessions
(
    site_id             UUID,
    session_id          String,
    visitor_id          String,
    started_at          DateTime64(3, 'UTC'),
    ended_at            DateTime64(3, 'UTC'),
    duration_s          UInt32,
    entry_page          String,
    exit_page           String,
    referrer            String,
    referrer_source     LowCardinality(String),
    utm_source          LowCardinality(String),
    utm_medium          LowCardinality(String),
    utm_campaign        String,
    country_code        LowCardinality(FixedString(2)),
    region              LowCardinality(String),
    city                String,
    device_type         LowCardinality(String),
    browser             LowCardinality(String),
    os                  LowCardinality(String),
    language            LowCardinality(String),
    pageview_count      UInt16,
    event_count         UInt16,
    bounce              UInt8,
    total_engagement_ms UInt32,
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
    toUInt32(dateDiff('second', min(timestamp), max(timestamp))) AS duration_s,
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
    toUInt32(ifNull(sumIf(engagement_time_ms, engagement_time_ms IS NOT NULL), 0)) AS total_engagement_ms,
    max(ingested_at)                        AS version
FROM statflow.events
GROUP BY site_id, session_id;

-- ---------------------------------------------------------------------------
-- 4. DAILY rollup (daily_stats_mv → daily_stats)
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
    pageviews           SimpleAggregateFunction(sum, UInt64),
    events              SimpleAggregateFunction(sum, UInt64),
    total_engagement_ms SimpleAggregateFunction(sum, UInt64),
    total_revenue       SimpleAggregateFunction(sum, Decimal(38, 4)),
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
    toUInt64(ifNull(sumIf(engagement_time_ms, engagement_time_ms IS NOT NULL), 0)) AS total_engagement_ms,
    ifNull(sumIf(revenue, revenue IS NOT NULL), 0) AS total_revenue,
    uniqState(visitor_id)                       AS unique_visitors,
    uniqState(session_id)                       AS unique_sessions
FROM statflow.events
GROUP BY site_id, toDate(timestamp), country_code, device_type, referrer_source, utm_source, utm_medium;

-- ---------------------------------------------------------------------------
-- 5. PER-PAGE rollup (page_stats_mv → page_stats)
-- ---------------------------------------------------------------------------
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
    toUInt64(ifNull(sumIf(engagement_time_ms, engagement_time_ms IS NOT NULL), 0)) AS total_engagement_ms,
    uniqState(visitor_id)                       AS unique_visitors,
    uniqState(session_id)                       AS unique_sessions,
    avgStateIf(scroll_depth_pct, scroll_depth_pct IS NOT NULL) AS scroll_depth_pct_avg
FROM statflow.events
GROUP BY site_id, toDate(timestamp), hostname, pathname;

-- ---------------------------------------------------------------------------
-- 6. PER-SOURCE rollup (source_stats_mv → source_stats)
-- ---------------------------------------------------------------------------
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
    ifNull(sumIf(revenue, revenue IS NOT NULL), 0) AS total_revenue,
    countIf(event_name = 'conversion')          AS conversions
FROM statflow.events
GROUP BY site_id, toDate(timestamp), referrer_source, utm_source, utm_medium, utm_campaign;

-- ---------------------------------------------------------------------------
-- 7. REALTIME — no stored table; the analytics API queries `events` over the
--    5-minute window. Redis counters (maintained in parallel by ingestion) back
--    the live counter and the SSE stream.
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- 8. HEATMAP grid (heatmap_stats_mv → heatmap_stats)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.heatmap_stats
(
    site_id             UUID,
    day                 Date,
    pathname            String,
    device_type         LowCardinality(String),
    bucket_x            UInt16,
    bucket_y            UInt16,
    click_count         UInt64,
    rage_click_count    UInt64
)
ENGINE = SummingMergeTree((click_count, rage_click_count))
PARTITION BY toYYYYMM(day)
ORDER BY (site_id, day, pathname, device_type, bucket_x, bucket_y)
TTL day + INTERVAL 12 MONTH
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
-- 9. SCROLL DEPTH bands (scroll_depth_stats_mv → scroll_depth_stats)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.scroll_depth_stats
(
    site_id             UUID,
    day                 Date,
    pathname            String,
    device_type         LowCardinality(String),
    depth_band          UInt8,
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
    toUInt8(floor(ifNull(scroll_depth_pct, 0) / 10) * 10) AS depth_band,
    uniqState(visitor_id)                           AS visitor_count
FROM statflow.events
WHERE event_name = 'scroll_depth'
    AND scroll_depth_pct IS NOT NULL
GROUP BY
    site_id, toDate(timestamp), pathname, device_type,
    toUInt8(floor(ifNull(scroll_depth_pct, 0) / 10) * 10);

-- ---------------------------------------------------------------------------
-- 10. FUNNEL EVENTS projection
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.funnel_events
(
    site_id     UUID,
    funnel_id   UUID,
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
-- 11. RETENTION cohorts (nightly batch job, not an MV)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statflow.retention_cohorts
(
    site_id         UUID,
    cohort_week     Date,
    week_offset     UInt8,
    new_visitors    UInt64,
    retained        UInt64
)
ENGINE = ReplacingMergeTree
PARTITION BY toYYYYMM(cohort_week)
ORDER BY (site_id, cohort_week, week_offset)
TTL cohort_week + INTERVAL 24 MONTH
SETTINGS index_granularity = 8192;
