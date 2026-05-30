// =============================================================================
// heatmapModel — Unit tests (Behaviour screen pure logic)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: 100% lines + branches for heatmapModel.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  HEATMAP_VIEW_TYPES,
  DEVICE_KINDS,
  viewportRangeFor,
  apiHeatmapTypeFor,
  showsScrollOverlay,
  showsClickOverlay,
  eventNameFilterFor,
  topElementsFromBreakdown,
  scrollDepthBars,
  foldDepthPct,
  buildPageList,
  sampleSizeFromPoints,
  buildInsights,
  type HeatmapViewType,
} from '@/components/behaviour/heatmapModel'
import type { BreakdownRow, HeatmapClickPoint, HeatmapScrollDepth } from '@/api/types'

function row(value: string, events: number): BreakdownRow {
  return { value, metrics: { events } }
}

describe('heatmapModel — constants', () => {
  it('exposes the four view types in wireframe order', () => {
    expect(HEATMAP_VIEW_TYPES).toEqual(['clicks', 'scroll', 'movement', 'rage'])
  })

  it('exposes the three device kinds', () => {
    expect(DEVICE_KINDS).toEqual(['desktop', 'tablet', 'mobile'])
  })
})

describe('viewportRangeFor', () => {
  it('maps mobile to the < 768 band', () => {
    expect(viewportRangeFor('mobile')).toEqual({ viewport_width_min: 0, viewport_width_max: 767 })
  })

  it('maps tablet to the 768–1023 band', () => {
    expect(viewportRangeFor('tablet')).toEqual({
      viewport_width_min: 768,
      viewport_width_max: 1023,
    })
  })

  it('maps desktop to an open-ended ≥ 1024 band', () => {
    expect(viewportRangeFor('desktop')).toEqual({ viewport_width_min: 1024 })
  })
})

describe('apiHeatmapTypeFor', () => {
  it('returns scroll only for the scroll view', () => {
    expect(apiHeatmapTypeFor('scroll')).toBe('scroll')
  })

  it.each(['clicks', 'movement', 'rage'] as HeatmapViewType[])(
    'returns click for %s',
    (view) => {
      expect(apiHeatmapTypeFor(view)).toBe('click')
    },
  )
})

describe('overlay selectors', () => {
  it('shows the scroll overlay only for the scroll view', () => {
    expect(showsScrollOverlay('scroll')).toBe(true)
    expect(showsScrollOverlay('clicks')).toBe(false)
  })

  it('shows the click overlay for every non-scroll view', () => {
    expect(showsClickOverlay('scroll')).toBe(false)
    expect(showsClickOverlay('clicks')).toBe(true)
    expect(showsClickOverlay('movement')).toBe(true)
    expect(showsClickOverlay('rage')).toBe(true)
  })
})

describe('eventNameFilterFor', () => {
  it('filters to rage_click for the rage view', () => {
    expect(eventNameFilterFor('rage')).toEqual({
      property: 'event_name',
      operator: 'eq',
      value: 'rage_click',
    })
  })

  it('filters to click for clicks and movement views', () => {
    const expected = { property: 'event_name', operator: 'eq', value: 'click' }
    expect(eventNameFilterFor('clicks')).toEqual(expected)
    expect(eventNameFilterFor('movement')).toEqual(expected)
  })

  it('applies no event filter to the scroll view', () => {
    expect(eventNameFilterFor('scroll')).toBeNull()
  })
})

describe('topElementsFromBreakdown', () => {
  it('ranks selectors by count and computes share', () => {
    const result = topElementsFromBreakdown([
      row('.cta', 30),
      row('.nav', 10),
      row('.hero', 60),
    ])
    expect(result.map((r) => r.selector)).toEqual(['.hero', '.cta', '.nav'])
    expect(result[0].sharePct).toBeCloseTo(60)
    expect(result[1].sharePct).toBeCloseTo(30)
    expect(result[2].sharePct).toBeCloseTo(10)
  })

  it('labels blank selectors as unidentified', () => {
    const result = topElementsFromBreakdown([row('   ', 5)])
    expect(result[0].selector).toBe('(unidentified)')
  })

  it('drops zero-count rows', () => {
    const result = topElementsFromBreakdown([row('.a', 0), row('.b', 4)])
    expect(result).toHaveLength(1)
    expect(result[0].selector).toBe('.b')
  })

  it('returns shares of 0 when total is 0', () => {
    expect(topElementsFromBreakdown([row('.a', 0)])).toEqual([])
  })

  it('reads an alternative count metric when asked', () => {
    const result = topElementsFromBreakdown(
      [{ value: '.x', metrics: { pageviews: 7 } }],
      'pageviews',
    )
    expect(result[0].count).toBe(7)
  })

  it('treats a missing metric as zero', () => {
    expect(topElementsFromBreakdown([{ value: '.x', metrics: {} }])).toEqual([])
  })

  it('handles a missing value field defensively', () => {
    const result = topElementsFromBreakdown([
      { value: undefined as unknown as string, metrics: { events: 3 } },
    ])
    expect(result[0].selector).toBe('(unidentified)')
  })
})

describe('scrollDepthBars', () => {
  const depths: HeatmapScrollDepth[] = [
    { depth_pct: 25, sessions_pct: 80 },
    { depth_pct: 100, sessions_pct: 20 },
    { depth_pct: 50, sessions_pct: 55 },
  ]

  it('sorts deepest-first', () => {
    expect(scrollDepthBars(depths).map((b) => b.depthPct)).toEqual([100, 50, 25])
  })

  it('clamps session percentages into 0–100', () => {
    const result = scrollDepthBars([
      { depth_pct: 10, sessions_pct: 140 },
      { depth_pct: 20, sessions_pct: -5 },
    ])
    expect(result.find((b) => b.depthPct === 10)?.sessionsPct).toBe(100)
    expect(result.find((b) => b.depthPct === 20)?.sessionsPct).toBe(0)
  })

  it('returns an empty array for undefined input', () => {
    expect(scrollDepthBars(undefined)).toEqual([])
  })

  it('returns an empty array for empty input', () => {
    expect(scrollDepthBars([])).toEqual([])
  })
})

describe('foldDepthPct', () => {
  it('returns the deepest bucket still reached by the threshold', () => {
    const bars = scrollDepthBars([
      { depth_pct: 25, sessions_pct: 80 },
      { depth_pct: 50, sessions_pct: 55 },
      { depth_pct: 75, sessions_pct: 30 },
    ])
    expect(foldDepthPct(bars)).toBe(50)
  })

  it('honours a custom threshold', () => {
    const bars = scrollDepthBars([
      { depth_pct: 25, sessions_pct: 80 },
      { depth_pct: 50, sessions_pct: 55 },
    ])
    expect(foldDepthPct(bars, 70)).toBe(25)
  })

  it('returns null when no bucket clears the threshold', () => {
    const bars = scrollDepthBars([{ depth_pct: 100, sessions_pct: 10 }])
    expect(foldDepthPct(bars)).toBeNull()
  })

  it('returns null for an empty distribution', () => {
    expect(foldDepthPct([])).toBeNull()
  })
})

describe('buildPageList', () => {
  const rows = [row('/pricing', 8203), row('/docs', 4812), row('/', 6991)]

  it('ranks by clicks descending by default', () => {
    const result = buildPageList(rows, '', 'clicks')
    expect(result.map((r) => r.pathname)).toEqual(['/pricing', '/', '/docs'])
  })

  it('sizes bars against the busiest page', () => {
    const result = buildPageList(rows, '', 'clicks')
    expect(result[0].barPct).toBe(100)
    expect(result[1].barPct).toBeCloseTo((6991 / 8203) * 100)
  })

  it('sorts alphabetically by path when asked', () => {
    const result = buildPageList(rows, '', 'path')
    expect(result.map((r) => r.pathname)).toEqual(['/', '/docs', '/pricing'])
  })

  it('filters case-insensitively on the search term', () => {
    const result = buildPageList(rows, 'DOCS', 'clicks')
    expect(result.map((r) => r.pathname)).toEqual(['/docs'])
  })

  it('returns every row when the search term is blank', () => {
    expect(buildPageList(rows, '   ', 'clicks')).toHaveLength(3)
  })

  it('handles an empty breakdown without dividing by zero', () => {
    expect(buildPageList([], '', 'clicks')).toEqual([])
  })

  it('treats a missing event metric as zero clicks', () => {
    const result = buildPageList([{ value: '/x', metrics: {} }], '', 'clicks')
    expect(result[0].clicks).toBe(0)
    expect(result[0].barPct).toBe(0)
  })
})

describe('sampleSizeFromPoints', () => {
  it('sums the bucket counts', () => {
    const points: HeatmapClickPoint[] = [
      { x_pct: 1, y_pct: 2, count: 3, weight: 0.5 },
      { x_pct: 4, y_pct: 5, count: 7, weight: 1 },
    ]
    expect(sampleSizeFromPoints(points)).toBe(10)
  })

  it('returns 0 for no points', () => {
    expect(sampleSizeFromPoints([])).toBe(0)
  })
})

describe('buildInsights', () => {
  it('tags rows with the requested kind and ranks them', () => {
    const result = buildInsights([row('.submit', 12), row('.link', 3)], 'rage')
    expect(result[0]).toMatchObject({ kind: 'rage', selector: '.submit', count: 12 })
    expect(result[1].kind).toBe('rage')
  })

  it('supports the dead-click kind', () => {
    const result = buildInsights([row('.banner', 4)], 'dead')
    expect(result[0].kind).toBe('dead')
    expect(result[0].sharePct).toBeCloseTo(100)
  })

  it('returns an empty array when there is nothing to rank', () => {
    expect(buildInsights([], 'rage')).toEqual([])
  })
})
