// =============================================================================
// Statflow Dashboard — ECharts option builder tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  buildTimeSeriesOption,
  buildBarOption,
  buildDonutOption,
  buildSparkLineOption,
  buildRetentionOption,
  buildGeoMapOption,
  type ChartFormatters,
} from '@/charts/options'

const fmt: ChartFormatters = {
  value: (n) => `${n}`,
  axisDate: (label) => `D:${label}`,
}

describe('buildTimeSeriesOption', () => {
  it('enables aria and builds one line series per input', () => {
    const option = buildTimeSeriesOption(
      ['2025-06-01', '2025-06-02'],
      [{ name: 'Sessions', data: [10, 20] }],
      fmt,
    )
    expect(option.aria).toEqual({ enabled: true })
    expect(Array.isArray(option.series)).toBe(true)
    expect((option.series as unknown[]).length).toBe(1)
  })

  it('hides the legend for a single series and shows it for multiple', () => {
    const single = buildTimeSeriesOption(['a'], [{ name: 'x', data: [1] }], fmt)
    expect((single.legend as { show?: boolean }).show).toBe(false)
    const multi = buildTimeSeriesOption(
      ['a'],
      [
        { name: 'x', data: [1] },
        { name: 'y', data: [2] },
      ],
      fmt,
    )
    expect((multi.legend as { show?: boolean }).show).toBeUndefined()
  })

  it('renders the comparison series as a dashed line', () => {
    const option = buildTimeSeriesOption(
      ['a'],
      [
        { name: 'now', data: [1] },
        { name: 'prev', data: [1], comparison: true },
      ],
      fmt,
    )
    const series = option.series as Array<{ lineStyle: { type?: string } }>
    expect(series[1].lineStyle.type).toBe('dashed')
  })

  it('adds dataZoom when requested', () => {
    const zoomed = buildTimeSeriesOption(['a'], [{ name: 'x', data: [1] }], fmt, { showZoom: true })
    expect(zoomed.dataZoom).toBeDefined()
    const plain = buildTimeSeriesOption(['a'], [{ name: 'x', data: [1] }], fmt, { showZoom: false })
    expect(plain.dataZoom).toBeUndefined()
  })
})

describe('buildBarOption', () => {
  it('puts the category axis on Y when horizontal', () => {
    const option = buildBarOption(['/a', '/b'], [5, 3], fmt, { horizontal: true })
    expect((option.yAxis as { type: string }).type).toBe('category')
    expect((option.xAxis as { type: string }).type).toBe('value')
  })

  it('puts the category axis on X when vertical', () => {
    const option = buildBarOption(['a'], [5], fmt, { horizontal: false })
    expect((option.xAxis as { type: string }).type).toBe('category')
  })
})

describe('buildDonutOption', () => {
  it('collapses slices below 1% into Other', () => {
    const option = buildDonutOption(
      [
        { name: 'Desktop', value: 990 },
        { name: 'Tiny', value: 1 },
      ],
      fmt,
      'Other',
    )
    const data = (option.series as Array<{ data: Array<{ name: string }> }>)[0].data
    expect(data.some((d) => d.name === 'Other')).toBe(true)
  })

  it('keeps all slices when each is significant', () => {
    const option = buildDonutOption(
      [
        { name: 'A', value: 60 },
        { name: 'B', value: 40 },
      ],
      fmt,
    )
    const data = (option.series as Array<{ data: unknown[] }>)[0].data
    expect(data).toHaveLength(2)
  })
})

describe('buildSparkLineOption', () => {
  it('hides axes and tooltip', () => {
    const option = buildSparkLineOption([1, 2, 3], 'up')
    expect((option.xAxis as { show: boolean }).show).toBe(false)
    expect((option.yAxis as { show: boolean }).show).toBe(false)
    expect((option.tooltip as { show: boolean }).show).toBe(false)
  })

  it('colours by trend direction', () => {
    const up = buildSparkLineOption([1], 'up')
    const down = buildSparkLineOption([1], 'down')
    expect(up.color).not.toEqual(down.color)
  })
})

describe('buildRetentionOption', () => {
  it('builds heatmap cells with a hidden visualMap range', () => {
    const option = buildRetentionOption(
      ['W1'],
      ['0', '1'],
      [
        [0, 0, 100],
        [1, 0, 42],
      ],
      fmt,
    )
    expect((option.series as Array<{ type: string }>)[0].type).toBe('heatmap')
    expect(option.visualMap).toBeDefined()
  })
})

describe('buildGeoMapOption', () => {
  it('binds the map by name and scales the visualMap to the max value', () => {
    const option = buildGeoMapOption(
      'world',
      [
        { name: 'France', value: 100 },
        { name: 'Germany', value: 50 },
      ],
      fmt,
    )
    expect((option.series as Array<{ map: string }>)[0].map).toBe('world')
    expect((option.visualMap as { max: number }).max).toBe(100)
  })

  it('defaults the max to 1 for empty data to avoid a zero range', () => {
    const option = buildGeoMapOption('world', [], fmt)
    expect((option.visualMap as { max: number }).max).toBe(1)
  })
})
