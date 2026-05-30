// =============================================================================
// realtimeChart — Unit tests (Realtime trend option builder)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: 100% lines + branches for realtimeChart.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import { buildRealtimeTrendOption } from '@/components/realtime/realtimeChart'
import type { TrendBucket } from '@/components/realtime/realtimeModel'
import type { ChartFormatters } from '@/charts/options'

const fmt: ChartFormatters = { value: (v) => `#${v}` }

function buckets(counts: number[]): TrendBucket[] {
  return counts.map((count, i) => ({ minute: `12:${String(i).padStart(2, '0')}`, count }))
}

describe('buildRealtimeTrendOption', () => {
  it('enables aria and animation by default', () => {
    const option = buildRealtimeTrendOption(buckets([1, 2, 3]), fmt)
    expect(option.aria).toEqual({ enabled: true })
    expect(option.animation).toBe(true)
  })

  it('disables animation when requested (reduced motion)', () => {
    const option = buildRealtimeTrendOption(buckets([1]), fmt, { animate: false })
    expect(option.animation).toBe(false)
  })

  it('maps bucket counts to bar data and highlights the live (last) bar', () => {
    const option = buildRealtimeTrendOption(buckets([4, 7, 9]), fmt)
    const series = (option.series as Array<{ data: Array<{ value: number; itemStyle: { color: string } }> }>)[0]
    expect(series.data.map((d) => d.value)).toEqual([4, 7, 9])
    const liveColor = series.data[2].itemStyle.color
    const pastColor = series.data[0].itemStyle.color
    expect(liveColor).not.toBe(pastColor)
  })

  it('uses the minute labels for the x-axis categories', () => {
    const option = buildRealtimeTrendOption(buckets([1, 1]), fmt)
    const xAxis = option.xAxis as { data: string[] }
    expect(xAxis.data).toEqual(['12:00', '12:01'])
  })

  it('thins x-axis labels but always keeps the last bucket', () => {
    const option = buildRealtimeTrendOption(buckets(Array.from({ length: 30 }, () => 1)), fmt)
    const xAxis = option.xAxis as { axisLabel: { interval: (index: number) => boolean } }
    expect(xAxis.axisLabel.interval(29)).toBe(true)
    expect(xAxis.axisLabel.interval(0)).toBe(true)
    expect(xAxis.axisLabel.interval(1)).toBe(false)
  })

  it('formats the tooltip from a single axis point and an array of points', () => {
    const option = buildRealtimeTrendOption(buckets([1]), fmt)
    const tooltip = option.tooltip as { formatter: (params: unknown) => string }
    expect(tooltip.formatter({ axisValue: '12:00', value: 5 })).toBe('12:00 · #5')
    expect(tooltip.formatter([{ axisValue: '12:01', value: 8 }])).toBe('12:01 · #8')
  })

  it('formats the y-axis values through the provided formatter', () => {
    const option = buildRealtimeTrendOption(buckets([1]), fmt)
    const yAxis = option.yAxis as { axisLabel: { formatter: (v: number) => string }; minInterval: number }
    expect(yAxis.axisLabel.formatter(3)).toBe('#3')
    expect(yAxis.minInterval).toBe(1)
  })
})
