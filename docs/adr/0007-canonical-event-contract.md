# 0007 — Canonical Event Contract

Date: 2026-05-16

## Status

Accepted

## Context

The event payload is the single most load-bearing artefact in Statflow: the
tracker produces it, the ingestion context validates it, ClickHouse stores it,
and every analytics query reads it. Five specification sets were drafted in
parallel and produced **three mutually incompatible definitions** of an
ingested event:

- `docs/api/openapi.yaml` (`EventPayload`, `BehavioralSignals`) — long field
  names, ISO-8601 timestamps, nested `behavioral` object, `custom_properties`
  at the top level.
- `docs/tracker/event-taxonomy.md` — short wire keys (`vid`, `sid`, `ts`,
  `vw`, …), Unix-millisecond timestamps, a `properties` object per event type.
- `docs/data-model/clickhouse-schema.sql` (`events` table) — a third set of
  column names and types, additional columns (`click_tx`, `is_rage_click`,
  `revenue`) absent from both of the above.

The event-name vocabulary also diverged (`scroll` vs `scroll_depth`,
`pageview`/`click`/`scroll`/`engagement` vs a 17-name taxonomy), and the batch
envelope had two different root keys (`events` vs `batch`).

A developer starting the Ingestion context today would have to guess which of
three schemas is authoritative. No Ingestion or Analytics code can be written
until this is resolved.

## Decision

There is **one canonical event model** and **one compact wire format**, related
by an explicit, normative mapping. Both live in
`docs/data-model/event-contract.md`, which is the single source of truth. The
OpenAPI `EventPayload`, the tracker envelope, and the ClickHouse `events`
columns are all defined to be consistent with that document.

1. **Canonical model.** The canonical event uses long, descriptive field names
   (`event_name`, `visitor_id`, `session_id`, `viewport_width`, …). The API
   request schema and the ClickHouse `events` table both use the canonical
   names. This is the form a backend engineer, an analyst writing SQL, and a
   consumer of the Stats API all see.

2. **Wire format.** The browser tracker emits a **compact** wire form with
   short keys (`e`, `ts`, `u`, `vw`, …) to minimise payload size. The wire
   format is an optimisation detail of the tracker transport only.

3. **Normalisation at the ingestion boundary.** The Ingestion context performs
   exactly one transformation — wire → canonical — immediately on receipt,
   before validation, enrichment, or buffering. Nothing downstream of the
   ingestion handler ever sees short keys. The wire↔canonical mapping table in
   `event-contract.md` is normative; the ingestion normaliser is its
   implementation.

4. **Server-derived identity fields are not wire fields.** `visitor_id` and
   `session_id` are computed server-side (see ADR-0008) and are therefore
   **absent from the wire format and from the API request schema**. They exist
   only in the canonical model and in ClickHouse.

5. **Unified event-name vocabulary.** One closed vocabulary of event names is
   defined in `event-contract.md §4` and is identical in the tracker, the API
   (`event_name` description and reserved-name list), and the ClickHouse
   `event_name` column comment. The damaging `scroll` vs `scroll_depth`
   mismatch is resolved in favour of `scroll_depth`.

6. **Unified batch envelope.** One batch envelope with root key `events` and
   metadata fields `sent_at`, `sdk`, `sdk_v`. The tracker, OpenAPI
   `/events/batch`, and the README all use it.

7. **One transport content type.** Both the `fetch` and `sendBeacon` paths send
   `Content-Type: text/plain` with a JSON-encoded body. The ingestion endpoint
   parses the body as JSON regardless of content type. This removes the CORS
   preflight from every request (not only beacon flushes) and gives the two
   transport paths identical behaviour. Request bodies are never gzip-encoded
   by the tracker; the ingestion endpoint MAY accept `Content-Encoding: gzip`
   from server-side integrations but the browser tracker does not rely on it.

8. **`event_id` and `seq`.** The tracker generates a per-event `event_id`
   (UUIDv4) for idempotency and a per-session monotonic `seq` counter for
   ordered analysis. Both are wire fields, both are canonical fields, and both
   are stored in ClickHouse. (`core/ids.ts` generates them; this supersedes the
   tracker design's "no client-side ID generation" note.)

## Consequences

- Ingestion, Analytics, and Tracker work is unblocked: there is exactly one
  contract to build against.
- The ingestion handler gains a small, well-defined normalisation step. Its
  cost is negligible (a key-rename pass over a sub-kilobyte object).
- The OpenAPI request schema documents canonical names; integrators sending
  events server-side use canonical names directly and skip the compaction step.
- ClickHouse gains `event_id`, `seq`, and a `tracker_version` column so that no
  collected field is silently dropped.
- The wire format can evolve (new short keys, new compaction) without an API
  break, as long as the mapping table and the normaliser are updated together.
- Web Vitals and `js_error` events are retained in the taxonomy but explicitly
  scoped to Jalon 2 autocapture; until their rollup tables exist they are
  stored in `events.properties` only. `heatmap_batch` is a Jalon 3 concern (see
  `event-contract.md §4`).
