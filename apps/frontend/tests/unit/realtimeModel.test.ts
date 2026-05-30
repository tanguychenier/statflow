// =============================================================================
// realtimeModel — Unit tests (Realtime screen pure logic)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: 100% lines + branches for realtimeModel.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  TREND_BUCKET_COUNT,
  MAX_EVENTS_DESKTOP,
  MAX_EVENTS_MOBILE,
  appendEvent,
  rankRows,
  topPagesRows,
  topSourcesRows,
  topReferrers,
  topCountries,
  mobileSharePct,
  eventsPerMinute,
  eventsInWindow,
  topPageLabel,
  buildTrend,
  eventTone,
  eventClock,
  environmentLabel,
  type LiveEventRow,
} from '@/components/realtime/realtimeModel'
import type { RealtimeEvent, RealtimeResponse } from '@/api/types'

const BASE = Date.parse('2026-05-29T12:00:00.000Z')

function rtEvent(overrides: Partial<RealtimeEvent> = {}): RealtimeEvent {
  return {
    timestamp: new Date(BASE).toISOString(),
    event_name: 'page_view',
    pathname: '/',
    referrer_source: 'google.com',
    country: 'US',
    device_type: 'desktop',
    browser: 'Chrome',
    ...overrides,
  }
}

function liveRow(overrides: Partial<LiveEventRow> = {}): LiveEventRow {
  return {
    id: 'id',
    timestamp: new Date(BASE).toISOString(),
    eventName: 'page_view',
    pathname: '/',
    source: 'google.com',
    country: 'US',
    device: 'desktop',
    browser: 'Chrome',
    ...overrides,
  }
}

function stats(overrides: Partial<RealtimeResponse> = {}): RealtimeResponse {
  return {
    current_visitors: 10,
    top_pages: [],
    top_sources: [],
    updated_at: new Date(BASE).toISOString(),
    ...overrides,
  }
}

describe('realtimeModel — constants', () => {
  it('exposes the 30-bucket trend width and viewport caps', () => {
    expect(TREND_BUCKET_COUNT).toBe(30)
    expect(MAX_EVENTS_DESKTOP).toBe(200)
    expect(MAX_EVENTS_MOBILE).toBe(100)
  })
})

describe('appendEvent', () => {
  it('prepends newest-first and assigns a stable composite id', () => {
    const e = rtEvent({ pathname: '/pricing' })
    const next = appendEvent([], e, 1, 200)
    expect(next).toHaveLength(1)
    expect(next[0].pathname).toBe('/pricing')
    expect(next[0].id).toBe(`${e.timestamp}-1`)
  })

  it('keeps the buffer within the cap, dropping the oldest', () => {
    let buffer: LiveEventRow[] = []
    for (let i = 0; i < 5; i += 1) {
      buffer = appendEvent(buffer, rtEvent({ pathname: `/p${i}` }), i, 3)
    }
    expect(buffer).toHaveLength(3)
    expect(buffer[0].pathname).toBe('/p4')
    expect(buffer[2].pathname).toBe('/p2')
  })

  it('maps every wire field onto the row', () => {
    const row = appendEvent(
      [],
      rtEvent({
        event_name: 'click',
        referrer_source: 'twitter.com',
        country: 'FR',
        device_type: 'mobile',
        browser: 'Safari',
      }),
      7,
      10,
    )[0]
    expect(row).toMatchObject({
      eventName: 'click',
      source: 'twitter.com',
      country: 'FR',
      device: 'mobile',
      browser: 'Safari',
    })
  })
})

describe('rankRows', () => {
  it('sorts by value descending then label, caps to the limit, sets bar percentages', () => {
    const rows = rankRows(
      [
        { key: 'a', label: 'A', value: 50 },
        { key: 'b', label: 'B', value: 100 },
        { key: 'c', label: 'C', value: 100 },
        { key: 'd', label: 'D', value: 10 },
      ],
      3,
    )
    expect(rows.map((r) => r.key)).toEqual(['b', 'c', 'a'])
    expect(rows[0].barPct).toBe(100)
    expect(rows[2].barPct).toBe(50)
  })

  it('returns 0 bar percentages when every value is zero', () => {
    const rows = rankRows([{ key: 'a', label: 'A', value: 0 }], 5)
    expect(rows[0].barPct).toBe(0)
  })

  it('returns an empty list for empty input', () => {
    expect(rankRows([], 5)).toEqual([])
  })
})

describe('topPagesRows / topSourcesRows', () => {
  it('returns an empty array when stats is null', () => {
    expect(topPagesRows(null, 5)).toEqual([])
    expect(topSourcesRows(null, 5)).toEqual([])
  })

  it('ranks pages from the stats payload', () => {
    const rows = topPagesRows(
      stats({
        top_pages: [
          { pathname: '/', visitors: 5 },
          { pathname: '/pricing', visitors: 20 },
        ],
      }),
      5,
    )
    expect(rows[0].label).toBe('/pricing')
    expect(rows[0].barPct).toBe(100)
  })

  it('labels empty referrer domains as "direct"', () => {
    const rows = topSourcesRows(
      stats({ top_sources: [{ referrer_domain: '', visitors: 9 }] }),
      5,
    )
    expect(rows[0].key).toBe('direct')
    expect(rows[0].label).toBe('direct')
  })
})

describe('eventsInWindow', () => {
  it('keeps only events within the trailing 5 minutes', () => {
    const recent = liveRow({ timestamp: new Date(BASE - 4 * 60_000).toISOString() })
    const old = liveRow({ timestamp: new Date(BASE - 6 * 60_000).toISOString() })
    const result = eventsInWindow([recent, old], BASE)
    expect(result).toEqual([recent])
  })

  it('drops events with unparseable timestamps', () => {
    const bad = liveRow({ timestamp: 'not-a-date' })
    expect(eventsInWindow([bad], BASE)).toEqual([])
  })
})

describe('topCountries', () => {
  it('tallies recent events by country, ranked, ignoring blanks and stale rows', () => {
    const events = [
      liveRow({ country: 'US' }),
      liveRow({ country: 'US' }),
      liveRow({ country: 'FR' }),
      liveRow({ country: '' }),
      liveRow({ country: 'DE', timestamp: new Date(BASE - 10 * 60_000).toISOString() }),
    ]
    const rows = topCountries(events, BASE, 5)
    expect(rows[0]).toMatchObject({ key: 'US', value: 2 })
    expect(rows.map((r) => r.key)).toEqual(['US', 'FR'])
  })
})

describe('topReferrers', () => {
  it('tallies recent referrers, folding blanks into "direct", ranked', () => {
    const events = [
      liveRow({ source: 'google.com' }),
      liveRow({ source: 'google.com' }),
      liveRow({ source: '' }),
      liveRow({ source: 'twitter.com' }),
      liveRow({ source: 'old.com', timestamp: new Date(BASE - 10 * 60_000).toISOString() }),
    ]
    const rows = topReferrers(events, BASE, 5)
    expect(rows[0]).toMatchObject({ key: 'google.com', value: 2 })
    expect(rows.map((r) => r.key)).toContain('direct')
    expect(rows.map((r) => r.key)).not.toContain('old.com')
  })

  it('returns an empty list when there are no recent events', () => {
    expect(topReferrers([], BASE, 5)).toEqual([])
  })
})

describe('mobileSharePct', () => {
  it('returns 0 when there are no recent events', () => {
    expect(mobileSharePct([], BASE)).toBe(0)
  })

  it('computes the rounded mobile percentage of the window', () => {
    const events = [
      liveRow({ device: 'mobile' }),
      liveRow({ device: 'Mobile' }),
      liveRow({ device: 'desktop' }),
    ]
    expect(mobileSharePct(events, BASE)).toBe(67)
  })
})

describe('eventsPerMinute', () => {
  it('counts only events in the trailing 60 seconds', () => {
    const events = [
      liveRow({ timestamp: new Date(BASE - 10_000).toISOString() }),
      liveRow({ timestamp: new Date(BASE - 59_000).toISOString() }),
      liveRow({ timestamp: new Date(BASE - 61_000).toISOString() }),
      liveRow({ timestamp: 'bad' }),
    ]
    expect(eventsPerMinute(events, BASE)).toBe(2)
  })
})

describe('topPageLabel', () => {
  it('returns null when stats is null or has no pages', () => {
    expect(topPageLabel(null)).toBeNull()
    expect(topPageLabel(stats({ top_pages: [] }))).toBeNull()
  })

  it('returns the busiest page', () => {
    const label = topPageLabel(
      stats({
        top_pages: [
          { pathname: '/', visitors: 3 },
          { pathname: '/docs', visitors: 9 },
        ],
      }),
    )
    expect(label).toBe('/docs')
  })
})

describe('buildTrend', () => {
  it('produces exactly 30 zero-filled buckets when there are no events', () => {
    const buckets = buildTrend([], BASE)
    expect(buckets).toHaveLength(30)
    expect(buckets.every((b) => b.count === 0)).toBe(true)
  })

  it('places events into their minute bucket and ignores out-of-range / bad rows', () => {
    const events = [
      liveRow({ timestamp: new Date(BASE).toISOString() }),
      liveRow({ timestamp: new Date(BASE - 60_000).toISOString() }),
      liveRow({ timestamp: new Date(BASE - 60 * 60_000).toISOString() }), // 60 min ago, dropped
      liveRow({ timestamp: 'bad' }),
    ]
    const buckets = buildTrend(events, BASE)
    expect(buckets[buckets.length - 1].count).toBe(1)
    expect(buckets[buckets.length - 2].count).toBe(1)
    expect(buckets.reduce((sum, b) => sum + b.count, 0)).toBe(2)
  })

  it('formats minute labels as zero-padded HH:mm', () => {
    const buckets = buildTrend([], Date.parse('2026-05-29T08:05:00.000Z'))
    expect(buckets[buckets.length - 1].minute).toMatch(/^\d{2}:\d{2}$/)
  })
})

describe('eventTone', () => {
  it('maps conversions, pageviews, interactions and the default', () => {
    expect(eventTone('conversion')).toBe('conversion')
    expect(eventTone('purchase')).toBe('conversion')
    expect(eventTone('goal')).toBe('conversion')
    expect(eventTone('page_view')).toBe('pageview')
    expect(eventTone('pageview')).toBe('pageview')
    expect(eventTone('click')).toBe('interaction')
    expect(eventTone('rage_click')).toBe('interaction')
    expect(eventTone('dead_click')).toBe('interaction')
    expect(eventTone('custom_thing')).toBe('default')
  })
})

describe('eventClock', () => {
  it('formats a parseable timestamp as HH:mm:ss', () => {
    expect(eventClock('2026-05-29T03:07:09Z')).toMatch(/^\d{2}:\d{2}:\d{2}$/)
  })

  it('falls back to the raw value for an unparseable timestamp', () => {
    expect(eventClock('nope')).toBe('nope')
  })
})

describe('environmentLabel', () => {
  it('joins browser and device with a separator', () => {
    expect(environmentLabel('Chrome', 'desktop')).toBe('Chrome · desktop')
  })

  it('omits empty parts', () => {
    expect(environmentLabel('Firefox', '')).toBe('Firefox')
    expect(environmentLabel('', '')).toBe('')
  })
})
