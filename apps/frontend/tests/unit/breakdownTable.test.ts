// =============================================================================
// breakdownTable — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for breakdownTable.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  buildBreakdownRows,
  buildDonutSlices,
  buildGeoData,
} from '@/components/overview/breakdownTable'
import type { BreakdownResponse } from '@/api/types'

function breakdown(
  rows: Array<{ value: string; visitors?: number; sessions?: number }>,
  property = 'pathname',
): BreakdownResponse {
  return {
    property,
    period: { from: '2024-12-01', to: '2024-12-31' },
    rows: rows.map((r) => ({
      value: r.value,
      metrics: { visitors: r.visitors ?? 0, sessions: r.sessions ?? 0 },
    })),
    total_rows: rows.length,
  }
}

describe('buildBreakdownRows — ranking & shares', () => {
  it('returns an empty array for a null response', () => {
    expect(buildBreakdownRows(null)).toEqual([])
  })

  it('returns an empty array when there are no rows', () => {
    expect(buildBreakdownRows(breakdown([]))).toEqual([])
  })

  it('sorts rows by the metric descending', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 100 },
        { value: '/b', visitors: 300 },
        { value: '/c', visitors: 200 },
      ]),
    )
    expect(rows.map((r) => r.value)).toEqual(['/b', '/c', '/a'])
  })

  it('computes share of the total for each row', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 75 },
        { value: '/b', visitors: 25 },
      ]),
    )
    expect(rows[0].sharePct).toBeCloseTo(75)
    expect(rows[1].sharePct).toBeCloseTo(25)
  })

  it('sizes the bar relative to the leading row', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 200 },
        { value: '/b', visitors: 50 },
      ]),
    )
    expect(rows[0].barPct).toBe(100)
    expect(rows[1].barPct).toBe(25)
  })

  it('handles an all-zero metric without dividing by zero', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 0 },
        { value: '/b', visitors: 0 },
      ]),
    )
    expect(rows.every((r) => r.sharePct === 0 && r.barPct === 0)).toBe(true)
  })

  it('truncates to the requested limit after sorting', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 10 },
        { value: '/b', visitors: 50 },
        { value: '/c', visitors: 30 },
      ]),
      { limit: 2 },
    )
    expect(rows.map((r) => r.value)).toEqual(['/b', '/c'])
  })

  it('computes shares against the full total, not just the visible top-N', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 50 },
        { value: '/b', visitors: 50 },
      ]),
      { limit: 1 },
    )
    expect(rows[0].sharePct).toBeCloseTo(50)
  })

  it('uses the chosen metric for ranking', () => {
    const rows = buildBreakdownRows(
      breakdown([
        { value: '/a', visitors: 10, sessions: 99 },
        { value: '/b', visitors: 99, sessions: 1 },
      ]),
      { metric: 'sessions' },
    )
    expect(rows[0].value).toBe('/a')
  })

  it('applies a label mapping when provided', () => {
    const rows = buildBreakdownRows(breakdown([{ value: 'FR', visitors: 10 }], 'country'), {
      labelFor: (v) => (v === 'FR' ? 'France' : v),
    })
    expect(rows[0].label).toBe('France')
  })

  it('defaults the label to the raw value', () => {
    const rows = buildBreakdownRows(breakdown([{ value: '/pricing', visitors: 10 }]))
    expect(rows[0].label).toBe('/pricing')
  })
})

describe('buildGeoData', () => {
  it('maps country codes to choropleth name/value pairs', () => {
    const data = buildGeoData(
      breakdown([{ value: 'US', visitors: 42 }], 'country'),
      (code) => (code === 'US' ? 'United States of America' : code),
    )
    expect(data).toEqual([{ name: 'United States of America', value: 42 }])
  })

  it('returns an empty array for a null response', () => {
    expect(buildGeoData(null, (c) => c)).toEqual([])
  })
})

describe('buildDonutSlices', () => {
  it('maps device codes to labelled slices', () => {
    const slices = buildDonutSlices(
      breakdown([{ value: 'desktop', visitors: 62 }], 'device_type'),
      (v) => (v === 'desktop' ? 'Desktop' : v),
    )
    expect(slices).toEqual([{ name: 'Desktop', value: 62 }])
  })

  it('returns an empty array for a null response', () => {
    expect(buildDonutSlices(null, (v) => v)).toEqual([])
  })
})
