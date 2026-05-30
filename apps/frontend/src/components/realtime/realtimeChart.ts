// =============================================================================
// Realtime — ECharts option builder for the active-users trend (screens.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Pure builder kept out of the .vue wrapper so the option shape is unit-testable
// without a canvas. A vertical bar per one-minute bucket; the rightmost (live)
// bar is tinted with the accent colour and, when motion is allowed, pulses via a
// CSS-driven shadow on the canvas container rather than per-bar animation (which
// ECharts cannot loop cheaply). Axis labels are thinned so 30 buckets stay
// legible on narrow viewports.
// =============================================================================

import type { EChartsOption } from '@/charts/echarts'
import type { ChartFormatters } from '@/charts/options'
import { CHART_COLORS } from '@/charts/themes'
import type { TrendBucket } from './realtimeModel'

const LIVE_BAR_COLOR = '#818cf8'
const PAST_BAR_COLOR = CHART_COLORS[0]

/**
 * Build the trend bar option. The last bucket is the live minute and is
 * highlighted; `animate` toggles ECharts' enter animation so reduced-motion
 * callers get an instant render.
 */
export function buildRealtimeTrendOption(
  buckets: TrendBucket[],
  fmt: ChartFormatters,
  options: { animate?: boolean } = { animate: true },
): EChartsOption {
  const lastIndex = buckets.length - 1
  const labelEvery = Math.max(1, Math.ceil(buckets.length / 6))

  return {
    aria: { enabled: true },
    animation: options.animate !== false,
    animationDuration: 300,
    tooltip: {
      trigger: 'axis',
      confine: true,
      axisPointer: { type: 'shadow' },
      formatter: (params: unknown) => {
        const point = Array.isArray(params) ? params[0] : params
        const p = point as { axisValue: string; value: number }
        return `${p.axisValue} · ${fmt.value(p.value)}`
      },
    },
    grid: { left: 8, right: 8, top: 12, bottom: 8, containLabel: true },
    xAxis: {
      type: 'category',
      data: buckets.map((bucket) => bucket.minute),
      axisTick: { show: false },
      axisLabel: {
        interval: (index: number) => index === lastIndex || index % labelEvery === 0,
      },
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      axisLabel: { formatter: (value: number) => fmt.value(value) },
    },
    series: [
      {
        type: 'bar',
        data: buckets.map((bucket, index) => ({
          value: bucket.count,
          itemStyle: {
            color: index === lastIndex ? LIVE_BAR_COLOR : PAST_BAR_COLOR,
            borderRadius: [3, 3, 0, 0],
          },
        })),
        barMaxWidth: 18,
      },
    ],
  }
}
