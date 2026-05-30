// =============================================================================
// Pages & Sources — table adapter tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for pagesSourcesTable.ts
// =============================================================================

import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  toRows,
  shapeRows,
  buildTableCsv,
  csvField,
  downloadCsv,
} from '@/components/pagessources/pagesSourcesTable'
import type { BreakdownResponse } from '@/api/types'

function response(rows: Array<{ value: string; visitors?: number; pageviews?: number; bounce_rate?: number; avg_duration?: number }>): BreakdownResponse {
  return {
    property: 'pathname',
    period: { from: '2024-12-01', to: '2024-12-31' },
    total_rows: rows.length,
    rows: rows.map((row) => ({
      value: row.value,
      metrics: {
        visitors: row.visitors,
        pageviews: row.pageviews,
        bounce_rate: row.bounce_rate,
        avg_duration: row.avg_duration,
      },
    })),
  }
}

describe('toRows', () => {
  it('returns an empty array for null / empty responses', () => {
    expect(toRows(null)).toEqual([])
    expect(toRows(undefined)).toEqual([])
    expect(toRows(response([]))).toEqual([])
  })

  it('defaults absent metrics to zero', () => {
    const rows = toRows(response([{ value: '/a', visitors: 10 }]))
    expect(rows[0].metrics.visitors).toBe(10)
    expect(rows[0].metrics.pageviews).toBe(0)
    expect(rows[0].metrics.bounce_rate).toBe(0)
    expect(rows[0].metrics.avg_duration).toBe(0)
  })
})

const sample = toRows(
  response([
    { value: '/pricing', visitors: 120, pageviews: 300 },
    { value: '/docs', visitors: 90, pageviews: 500 },
    { value: '/blog', visitors: 60, pageviews: 80 },
    { value: '/about', visitors: 30, pageviews: 40 },
  ]),
)

describe('shapeRows — sorting', () => {
  it('sorts by the active metric descending by default', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows.map((r) => r.value)).toEqual(['/pricing', '/docs', '/blog', '/about'])
  })

  it('sorts ascending when requested', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'asc', page: 1, pageSize: 25 })
    expect(out.rows.map((r) => r.value)).toEqual(['/about', '/blog', '/docs', '/pricing'])
  })

  it('can sort by a secondary metric', () => {
    const out = shapeRows(sample, { sortMetric: 'pageviews', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows[0].value).toBe('/docs')
  })

  it('breaks metric ties on the dimension value', () => {
    const tied = toRows(
      response([
        { value: '/b', visitors: 10 },
        { value: '/a', visitors: 10 },
      ]),
    )
    const out = shapeRows(tied, { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows.map((r) => r.value)).toEqual(['/a', '/b'])
  })
})

describe('shapeRows — search', () => {
  it('filters rows case-insensitively by the dimension value', () => {
    const out = shapeRows(sample, { search: 'DOC', sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows.map((r) => r.value)).toEqual(['/docs'])
    expect(out.filteredCount).toBe(1)
    expect(out.totalCount).toBe(4)
  })

  it('treats whitespace-only search as no filter', () => {
    const out = shapeRows(sample, { search: '   ', sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.filteredCount).toBe(4)
  })
})

describe('shapeRows — pagination', () => {
  it('slices the requested page', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 2, pageSize: 2 })
    expect(out.rows.map((r) => r.value)).toEqual(['/blog', '/about'])
    expect(out.page).toBe(2)
    expect(out.pageCount).toBe(2)
  })

  it('clamps an over-range page to the last page', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 99, pageSize: 2 })
    expect(out.page).toBe(2)
    expect(out.rows).toHaveLength(2)
  })

  it('clamps a non-positive page to 1', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 0, pageSize: 2 })
    expect(out.page).toBe(1)
  })

  it('treats a non-positive page size as 1', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 0 })
    expect(out.rows).toHaveLength(1)
    expect(out.pageCount).toBe(4)
  })

  it('returns a single empty page for an empty input', () => {
    const out = shapeRows([], { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows).toEqual([])
    expect(out.pageCount).toBe(1)
    expect(out.page).toBe(1)
  })
})

describe('shapeRows — inline bar', () => {
  it('scales the bar to the filtered leader', () => {
    const out = shapeRows(sample, { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows[0].barPct).toBe(100)
    expect(out.rows[1].barPct).toBeCloseTo(75)
  })

  it('uses zero bars when the leader metric is all zero', () => {
    const zeros = toRows(response([{ value: '/a', visitors: 0 }, { value: '/b', visitors: 0 }]))
    const out = shapeRows(zeros, { sortMetric: 'visitors', sortDirection: 'desc', page: 1, pageSize: 25 })
    expect(out.rows.every((r) => r.barPct === 0)).toBe(true)
  })
})

describe('csvField', () => {
  it('leaves plain values untouched', () => {
    expect(csvField('/pricing')).toBe('/pricing')
    expect(csvField(120)).toBe('120')
  })

  it('quotes and escapes special characters', () => {
    expect(csvField('a,b')).toBe('"a,b"')
    expect(csvField('say "hi"')).toBe('"say ""hi"""')
    expect(csvField('l1\nl2')).toBe('"l1\nl2"')
  })
})

describe('buildTableCsv', () => {
  const columns = [
    { metric: 'visitors' as const, header: 'Visitors' },
    { metric: 'pageviews' as const, header: 'Page views' },
  ]

  it('writes a header row then the sorted+filtered rows', () => {
    const csv = buildTableCsv(sample, 'Page', columns, { sortMetric: 'visitors', sortDirection: 'desc' })
    const lines = csv.split('\r\n')
    expect(lines[0]).toBe('Page,Visitors,Page views')
    expect(lines[1]).toBe('/pricing,120,300')
    expect(lines).toHaveLength(5)
  })

  it('honours the search filter', () => {
    const csv = buildTableCsv(sample, 'Page', columns, { search: 'blog', sortMetric: 'visitors', sortDirection: 'desc' })
    expect(csv.split('\r\n')).toHaveLength(2)
    expect(csv).toContain('/blog,60,80')
  })
})

describe('downloadCsv', () => {
  afterEach(() => vi.restoreAllMocks())

  it('creates an anchor, clicks it and revokes the URL', () => {
    const createObjectURL = vi.fn(() => 'blob:mock')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    expect(downloadCsv('pages.csv', 'a,b')).toBe(true)
    expect(createObjectURL).toHaveBeenCalledOnce()
    expect(clickSpy).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock')
    vi.unstubAllGlobals()
  })

  it('returns false when createObjectURL is unavailable', () => {
    vi.stubGlobal('URL', {})
    expect(downloadCsv('x.csv', 'a')).toBe(false)
    vi.unstubAllGlobals()
  })
})
