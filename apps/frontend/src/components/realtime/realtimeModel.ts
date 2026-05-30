// =============================================================================
// Realtime — pure data shaping for the live screen (screens.md §4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// All derivation logic for the Realtime screen lives here as side-effect-free
// functions so the view stays a declarative composition layer and every rule
// (KPI maths, 30-minute trend bucketing, list ranking, event formatting) is
// unit-testable without mounting a component or opening an SSE connection.
//
// The canonical realtime window is the last 5 minutes (ADR / openapi.yaml). The
// "Active users" bar chart shows a wider 30-minute trend for context, built from
// the buffered live-event timestamps; the 5-minute count remains the live KPI.
// =============================================================================

import type { RealtimeEvent, RealtimeResponse } from '@/api/types'

/** Width of the "Active users" trend chart, in one-minute buckets. */
export const TREND_BUCKET_COUNT = 30
const MS_PER_MINUTE = 60_000
const FIVE_MINUTES_MS = 5 * MS_PER_MINUTE

/** Newest-first cap for the live event panel — desktop 200, mobile 100 (§4.3). */
export const MAX_EVENTS_DESKTOP = 200
export const MAX_EVENTS_MOBILE = 100

export interface RankedRow {
  /** Stable key used for FLIP reordering and list keys. */
  key: string
  label: string
  value: number
  /** 0–100 width of the inline bar, relative to the leading row. */
  barPct: number
}

export interface TrendBucket {
  /** Bucket start, ISO minute label (HH:mm) — already locale-agnostic. */
  minute: string
  count: number
}

export interface LiveEventRow {
  /** Stable, monotonic key for list rendering and de-duplication. */
  id: string
  timestamp: string
  eventName: string
  pathname: string
  source: string
  country: string
  device: string
  browser: string
}

/**
 * Prepend a freshly streamed event and keep the buffer newest-first within the
 * cap. A monotonic sequence guarantees stable keys even when two events share a
 * millisecond timestamp (FLIP/keyed lists must not collide).
 */
export function appendEvent(
  buffer: LiveEventRow[],
  event: RealtimeEvent,
  seq: number,
  max: number,
): LiveEventRow[] {
  const row: LiveEventRow = {
    id: `${event.timestamp}-${seq}`,
    timestamp: event.timestamp,
    eventName: event.event_name,
    pathname: event.pathname,
    source: event.referrer_source,
    country: event.country,
    device: event.device_type,
    browser: event.browser,
  }
  return [row, ...buffer].slice(0, max)
}

/** Build the inline-bar percentage for a value relative to the list maximum. */
function barPctOf(value: number, max: number): number {
  if (max <= 0) return 0
  return Math.round((value / max) * 100)
}

/**
 * Rank already-aggregated `{ label, value }` pairs into bar rows, descending by
 * value then label for a stable order, capped to `limit`. Ties break on label
 * so FLIP reordering is deterministic across renders.
 */
export function rankRows(
  pairs: Array<{ key: string; label: string; value: number }>,
  limit: number,
): RankedRow[] {
  const sorted = [...pairs].sort((a, b) => b.value - a.value || a.label.localeCompare(b.label))
  const top = sorted.slice(0, limit)
  const max = top.reduce((acc, row) => Math.max(acc, row.value), 0)
  return top.map((row) => ({ ...row, barPct: barPctOf(row.value, max) }))
}

/** Top pages from the `stats` payload, ranked for the bar list. */
export function topPagesRows(stats: RealtimeResponse | null, limit: number): RankedRow[] {
  if (!stats) return []
  return rankRows(
    stats.top_pages.map((page) => ({
      key: page.pathname,
      label: page.pathname,
      value: page.visitors,
    })),
    limit,
  )
}

/** Top referring sources from the `stats` payload, ranked for the bar list. */
export function topSourcesRows(stats: RealtimeResponse | null, limit: number): RankedRow[] {
  if (!stats) return []
  return rankRows(
    stats.top_sources.map((source) => ({
      key: source.referrer_domain || 'direct',
      label: source.referrer_domain || 'direct',
      value: source.visitors,
    })),
    limit,
  )
}

/** Count occurrences of a string property across the recent (≤5 min) events. */
function tally<K extends keyof LiveEventRow>(
  events: LiveEventRow[],
  field: K,
): Map<string, number> {
  const counts = new Map<string, number>()
  for (const event of events) {
    const raw = String(event[field]).trim()
    if (!raw) continue
    counts.set(raw, (counts.get(raw) ?? 0) + 1)
  }
  return counts
}

/** Events whose timestamp falls inside the 5-minute realtime window from `now`. */
export function eventsInWindow(events: LiveEventRow[], now: number): LiveEventRow[] {
  const floor = now - FIVE_MINUTES_MS
  return events.filter((event) => {
    const ms = new Date(event.timestamp).getTime()
    return Number.isFinite(ms) && ms >= floor
  })
}

/**
 * Countries active in the last 5 minutes, ranked. Country codes are kept as
 * keys; label resolution (flag + name) is the caller's concern.
 */
export function topCountries(events: LiveEventRow[], now: number, limit: number): RankedRow[] {
  const recent = eventsInWindow(events, now)
  const counts = tally(recent, 'country')
  return rankRows(
    [...counts].map(([code, value]) => ({ key: code, label: code, value })),
    limit,
  )
}

/**
 * Referrers active in the last 5 minutes, tallied from the streamed `source`
 * field and ranked. This complements the stats-derived `top_sources` (which are
 * visitor-deduped) with a raw, event-level view; blank sources fall back to
 * "direct" so direct traffic is still represented.
 */
export function topReferrers(events: LiveEventRow[], now: number, limit: number): RankedRow[] {
  const recent = eventsInWindow(events, now)
  const counts = new Map<string, number>()
  for (const event of recent) {
    const source = event.source.trim() || 'direct'
    counts.set(source, (counts.get(source) ?? 0) + 1)
  }
  return rankRows(
    [...counts].map(([source, value]) => ({ key: source, label: source, value })),
    limit,
  )
}

/** Share of mobile traffic (0–100) across the 5-minute window; 0 when empty. */
export function mobileSharePct(events: LiveEventRow[], now: number): number {
  const recent = eventsInWindow(events, now)
  if (recent.length === 0) return 0
  const mobile = recent.filter((event) => event.device.toLowerCase() === 'mobile').length
  return Math.round((mobile / recent.length) * 100)
}

/**
 * Events-per-minute throughput: events in the trailing 60 seconds. A short
 * window keeps the KPI responsive to live spikes rather than smoothing them
 * across the full 5-minute buffer.
 */
export function eventsPerMinute(events: LiveEventRow[], now: number): number {
  const floor = now - MS_PER_MINUTE
  return events.filter((event) => {
    const ms = new Date(event.timestamp).getTime()
    return Number.isFinite(ms) && ms >= floor
  }).length
}

/** Busiest page label in the window, for the compact KPI tile ("top page"). */
export function topPageLabel(stats: RealtimeResponse | null): string | null {
  if (!stats || stats.top_pages.length === 0) return null
  const leader = stats.top_pages.reduce((best, page) =>
    page.visitors > best.visitors ? page : best,
  )
  return leader.pathname
}

function minuteLabel(date: Date): string {
  const hh = String(date.getHours()).padStart(2, '0')
  const mm = String(date.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

/**
 * 30-bucket, one-minute-wide trend of event volume ending at the bucket
 * containing `now`. Buckets are zero-filled so the chart keeps a stable x-axis
 * even before enough history has streamed in. The rightmost bucket is the live
 * one and is the bar the view pulses.
 */
export function buildTrend(events: LiveEventRow[], now: number): TrendBucket[] {
  const currentMinute = Math.floor(now / MS_PER_MINUTE)
  const buckets: TrendBucket[] = []
  const indexByMinute = new Map<number, number>()

  for (let i = TREND_BUCKET_COUNT - 1; i >= 0; i -= 1) {
    const minuteEpoch = currentMinute - i
    indexByMinute.set(minuteEpoch, buckets.length)
    buckets.push({ minute: minuteLabel(new Date(minuteEpoch * MS_PER_MINUTE)), count: 0 })
  }

  for (const event of events) {
    const ms = new Date(event.timestamp).getTime()
    if (!Number.isFinite(ms)) continue
    const target = indexByMinute.get(Math.floor(ms / MS_PER_MINUTE))
    if (target !== undefined) buckets[target].count += 1
  }

  return buckets
}

/** Badge tint for an event name in the live stream — conversions stand out. */
export type EventTone = 'conversion' | 'pageview' | 'interaction' | 'default'

export function eventTone(eventName: string): EventTone {
  switch (eventName) {
    case 'conversion':
    case 'purchase':
    case 'goal':
      return 'conversion'
    case 'page_view':
    case 'pageview':
      return 'pageview'
    case 'click':
    case 'rage_click':
    case 'dead_click':
      return 'interaction'
    default:
      return 'default'
  }
}

/** Clock-time (HH:mm:ss) for an event row; falls back to the raw value. */
export function eventClock(timestamp: string): string {
  const date = new Date(timestamp)
  if (Number.isNaN(date.getTime())) return timestamp
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

/** Compact "Browser/OS-ish" environment label for an event row. */
export function environmentLabel(browser: string, device: string): string {
  return [browser, device].filter(Boolean).join(' · ')
}
