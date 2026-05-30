// =============================================================================
// Statflow Dashboard — ECharts option builders
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Pure functions that turn typed data + a locale-aware formatter into ECharts
// option objects. Keeping these out of the .vue files makes them unit-testable
// without mounting a component and keeps the wrappers thin. All builders set
// `aria.enabled` so ECharts emits a textual <desc> for screen readers.
// =============================================================================

import type { EChartsOption } from './echarts'
import { CHART_COLORS } from './themes'

export interface SeriesInput {
  name: string
  data: number[]
  /** Render as a dashed comparison overlay at reduced opacity. */
  comparison?: boolean
}

export interface ChartFormatters {
  /** Format a value-axis / tooltip number (locale + compact notation). */
  value: (n: number) => string
  /** Format a category-axis date label. */
  axisDate?: (label: string) => string
}

const GRADIENT_STOPS = (color: string) => ({
  type: 'linear' as const,
  x: 0,
  y: 0,
  x2: 0,
  y2: 1,
  colorStops: [
    { offset: 0, color: `${color}55` },
    { offset: 1, color: `${color}05` },
  ],
})

/** Time-series area line, multi-series with optional dashed comparison overlay. */
export function buildTimeSeriesOption(
  categories: string[],
  series: SeriesInput[],
  fmt: ChartFormatters,
  options: { showZoom?: boolean } = {},
): EChartsOption {
  return {
    aria: { enabled: true },
    tooltip: { trigger: 'axis', confine: true },
    legend: series.length > 1 ? { top: 0, right: 0 } : { show: false },
    grid: { left: 8, right: 8, top: series.length > 1 ? 32 : 8, bottom: options.showZoom ? 48 : 24, containLabel: true },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: categories,
      axisLabel: fmt.axisDate ? { formatter: (value: string) => fmt.axisDate!(value) } : {},
    },
    yAxis: {
      type: 'value',
      axisLabel: { formatter: (value: number) => fmt.value(value) },
    },
    dataZoom: options.showZoom
      ? [
          { type: 'inside' },
          { type: 'slider', height: 20, bottom: 8 },
        ]
      : undefined,
    series: series.map((s, index) => ({
      name: s.name,
      type: 'line',
      smooth: true,
      symbol: 'none',
      lineStyle: s.comparison ? { width: 2, type: 'dashed', opacity: 0.6 } : { width: 2 },
      areaStyle: s.comparison
        ? { opacity: 0.15 }
        : { color: GRADIENT_STOPS(CHART_COLORS[index % CHART_COLORS.length]) },
      data: s.data,
    })),
  }
}

/** Horizontal bar chart for top-N breakdowns. */
export function buildBarOption(
  categories: string[],
  values: number[],
  fmt: ChartFormatters,
  options: { horizontal?: boolean } = { horizontal: true },
): EChartsOption {
  const valueAxis = { type: 'value' as const, axisLabel: { formatter: (v: number) => fmt.value(v) } }
  const categoryAxis = {
    type: 'category' as const,
    data: categories,
    axisLabel: { formatter: (label: string) => (label.length > 20 ? `${label.slice(0, 20)}…` : label) },
  }
  return {
    aria: { enabled: true },
    tooltip: { trigger: 'axis', confine: true, axisPointer: { type: 'shadow' } },
    grid: { left: 8, right: 24, top: 8, bottom: 8, containLabel: true },
    xAxis: options.horizontal ? valueAxis : categoryAxis,
    yAxis: options.horizontal ? { ...categoryAxis, inverse: true } : valueAxis,
    series: [
      {
        type: 'bar',
        data: values,
        barWidth: 20,
        itemStyle: { color: CHART_COLORS[0], borderRadius: 4 },
        label: {
          show: true,
          position: options.horizontal ? 'right' : 'top',
          formatter: (p: unknown) => fmt.value((p as { value: number }).value),
        },
      },
    ],
  }
}

/** Donut chart for device/browser split; segments below 1% collapse to "Other". */
export function buildDonutOption(
  slices: Array<{ name: string; value: number }>,
  fmt: ChartFormatters,
  otherLabel = 'Other',
): EChartsOption {
  const total = slices.reduce((sum, slice) => sum + slice.value, 0) || 1
  const significant = slices.filter((slice) => slice.value / total >= 0.01)
  const otherValue = total - significant.reduce((sum, slice) => sum + slice.value, 0)
  const data = otherValue > 0 ? [...significant, { name: otherLabel, value: otherValue }] : significant

  return {
    aria: { enabled: true },
    tooltip: {
      trigger: 'item',
      confine: true,
      formatter: (p: unknown) => {
        const item = p as { name: string; value: number; percent: number }
        return `${item.name}: ${fmt.value(item.value)} (${item.percent}%)`
      },
    },
    legend: { orient: 'vertical', right: 0, top: 'middle' },
    series: [
      {
        type: 'pie',
        radius: ['55%', '75%'],
        center: ['35%', '50%'],
        avoidLabelOverlap: true,
        label: { show: false },
        emphasis: { scaleSize: 4 },
        data,
      },
    ],
  }
}

/** Micro spark line (no axes) for the MetricCard. */
export function buildSparkLineOption(
  data: number[],
  direction: 'up' | 'down' | 'neutral',
): EChartsOption {
  const color =
    direction === 'up'
      ? 'var(--sf-positive)'
      : direction === 'down'
        ? 'var(--sf-negative)'
        : 'var(--sf-fg-muted)'
  const resolved = direction === 'up' ? '#10b981' : direction === 'down' ? '#f43f5e' : '#71717a'
  return {
    aria: { enabled: true },
    grid: { left: 0, right: 0, top: 4, bottom: 0 },
    xAxis: { type: 'category', show: false, boundaryGap: false, data: data.map((_, i) => i) },
    yAxis: { type: 'value', show: false, scale: true },
    tooltip: { show: false },
    series: [
      {
        type: 'line',
        data,
        smooth: true,
        symbol: 'none',
        lineStyle: { width: 1.5, color: resolved },
        areaStyle: { color: GRADIENT_STOPS(resolved), opacity: 0.6 },
        silent: true,
      },
    ],
    color: [color],
  }
}

/** Cohort retention heatmap with a continuous visualMap (data-viz.md §3.4). */
export function buildRetentionOption(
  cohortLabels: string[],
  periodLabels: string[],
  cells: Array<[number, number, number]>,
  fmt: ChartFormatters,
): EChartsOption {
  return {
    aria: { enabled: true },
    tooltip: {
      position: 'top',
      formatter: (p: unknown) => `${fmt.value((p as { value: [number, number, number] }).value[2])}`,
    },
    grid: { left: 8, right: 8, top: 24, bottom: 8, containLabel: true },
    xAxis: { type: 'category', data: periodLabels, splitArea: { show: true } },
    yAxis: { type: 'category', data: cohortLabels, splitArea: { show: true }, inverse: true },
    visualMap: {
      min: 0,
      max: 100,
      calculable: true,
      orient: 'horizontal',
      left: 'center',
      bottom: 0,
      show: false,
      inRange: { color: ['#27272a', '#6366f1'] },
    },
    series: [
      {
        type: 'heatmap',
        data: cells,
        label: {
          show: true,
          formatter: (p: unknown) => `${Math.round((p as { value: [number, number, number] }).value[2])}%`,
        },
      },
    ],
  }
}

/** Choropleth world map keyed on country name (data-viz.md §3.5). */
export function buildGeoMapOption(
  mapName: string,
  data: Array<{ name: string; value: number }>,
  fmt: ChartFormatters,
): EChartsOption {
  const max = data.reduce((acc, item) => Math.max(acc, item.value), 0) || 1
  return {
    aria: { enabled: true },
    tooltip: {
      trigger: 'item',
      formatter: (p: unknown) => {
        const item = p as { name: string; value: number }
        return `${item.name}: ${Number.isFinite(item.value) ? fmt.value(item.value) : '—'}`
      },
    },
    visualMap: {
      min: 0,
      max,
      left: 8,
      bottom: 8,
      calculable: true,
      inRange: { color: ['#312e81', '#818cf8'] },
      textStyle: { color: '#a0a0ab' },
    },
    series: [
      {
        type: 'map',
        map: mapName,
        roam: true,
        emphasis: { label: { show: false } },
        data,
      },
    ],
  }
}
