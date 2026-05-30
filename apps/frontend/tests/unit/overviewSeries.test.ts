// =============================================================================
// overviewSeries — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for overviewSeries.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import { buildOverviewSeries } from '@/components/overview/overviewSeries'
import type { TimeSeriesResponse } from '@/api/types'

function ts(values: number[], metric = 'visitors'): TimeSeriesResponse {
  return {
    interval: 'day',
    period: { from: '2024-12-01', to: '2024-12-03' },
    series: values.map((v, i) => ({
      bucket: `2024-12-0${i + 1}`,
      metrics: { [metric]: v },
    })),
  }
}

const labels = { currentLabel: 'Visitors', comparisonLabel: 'vs. previous period' }

describe('buildOverviewSeries', () => {
  it('returns empty categories and series for null input', () => {
    expect(buildOverviewSeries(null, labels)).toEqual({ categories: [], series: [] })
  })

  it('derives categories from the primary bucket timestamps', () => {
    const result = buildOverviewSeries(ts([10, 20, 30]), labels)
    expect(result.categories).toEqual(['2024-12-01', '2024-12-02', '2024-12-03'])
  })

  it('emits a single primary series labelled with currentLabel', () => {
    const result = buildOverviewSeries(ts([10, 20, 30]), labels)
    expect(result.series).toEqual([{ name: 'Visitors', data: [10, 20, 30] }])
  })

  it('uses the requested metric for the primary series', () => {
    const result = buildOverviewSeries(ts([5, 6, 7], 'sessions'), {
      ...labels,
      metric: 'sessions',
    })
    expect(result.series[0].data).toEqual([5, 6, 7])
  })

  it('defaults absent metric values to zero', () => {
    const series: TimeSeriesResponse = {
      interval: 'day',
      period: { from: '2024-12-01', to: '2024-12-02' },
      series: [
        { bucket: '2024-12-01', metrics: { visitors: 10 } },
        { bucket: '2024-12-02', metrics: {} },
      ],
    }
    expect(buildOverviewSeries(series, labels).series[0].data).toEqual([10, 0])
  })

  it('appends a dashed comparison overlay when bucket counts match', () => {
    const result = buildOverviewSeries(ts([10, 20, 30]), {
      ...labels,
      comparison: ts([8, 16, 24]),
    })
    expect(result.series).toHaveLength(2)
    expect(result.series[1]).toEqual({
      name: 'vs. previous period',
      data: [8, 16, 24],
      comparison: true,
    })
  })

  it('drops a misaligned comparison overlay rather than skewing the X-axis', () => {
    const result = buildOverviewSeries(ts([10, 20, 30]), {
      ...labels,
      comparison: ts([8, 16]),
    })
    expect(result.series).toHaveLength(1)
  })

  it('ignores a null comparison', () => {
    const result = buildOverviewSeries(ts([10, 20, 30]), { ...labels, comparison: null })
    expect(result.series).toHaveLength(1)
  })
})
