# 0006 — Event-Based Unified Data Model

Date: 2025-05-01

## Status

Accepted

## Context

Statflow must support two distinct analytics disciplines from a single data collection pipeline:

1. **Audience measurement** — pageviews, sessions, referrers, geography, device type (à la Plausible).
2. **Behavioural / product analytics** — custom events, funnel analysis, user journeys, heatmaps, rage clicks (à la PostHog / Microsoft Clarity).

A naive approach would use separate schemas for each: a `pageviews` table and a separate `events` table.  This creates duplication in the tracker payload, the ingestion pipeline, and the query layer, and makes it impossible to correlate audience and behavioural signals without complex joins.

Competing models considered:

| Model | Trade-offs |
|-------|-----------|
| Separate pageview and event tables | Simple schema but duplicated ingestion logic; no unified session model. |
| Session-centric (one row per session with arrays) | Efficient for session-level aggregates but expensive to mutate and poor for event-level drill-down. |
| Unified event stream | All signals are events; pageviews are simply events with `type = 'pageview'`.  Single ingestion path, single schema, maximal flexibility. |

## Decision

Statflow uses a **unified event-based data model**: every signal — pageview, custom event, click, scroll depth, rage click — is represented as a first-class event with a consistent envelope:

```
event_id        UUID (generated client-side or server-side)
site_id         UUID
session_id      UUID (cookieless, derived from fingerprint + sliding window)
timestamp       DateTime64(3, 'UTC')
type            LowCardinality(String)   -- 'pageview' | 'click' | 'custom' | …
url             String
referrer        String
properties      Map(String, String)      -- arbitrary key-value payload
device_type     LowCardinality(String)
browser         LowCardinality(String)
os              LowCardinality(String)
country_code    FixedString(2)
region          String
```

Audience metrics (unique visitors, session counts, bounce rate) and behavioural metrics (funnels, heatmap coordinates, custom event counts) are derived from the same event stream using different aggregation queries or materialised views.

The tracker emits a single event schema regardless of event type.  The ingestion API validates and normalises incoming payloads against this envelope before writing to ClickHouse.

## Consequences

- A single ingestion code path handles all event types — simpler tracker, simpler backend, simpler ClickHouse schema.
- New event types (e.g. `scroll_depth`, `form_submit`) are added by registering a new `type` value; no schema migration is required for the core table.
- Session reconstruction is performed server-side using a sliding time window and a cookieless fingerprint, preserving privacy while enabling journey analysis.
- The `properties` map provides schema flexibility at the cost of ClickHouse's columnar compression efficiency for those fields; high-cardinality properties should be extracted into dedicated columns via materialised views if query performance demands it.
- All bounded contexts (`Ingestion`, `Analytics`, `Reporting`) share the same event vocabulary, defined in the `Shared` context and documented in `docs/data-model/`.
