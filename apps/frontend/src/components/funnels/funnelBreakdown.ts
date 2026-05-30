// =============================================================================
// Funnels — breakdown adapter (screens.md §3.2 "Breakdown")
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Reshapes a BreakdownResponse (conversions grouped by device / country /
// source) into the { categories, values } pair BarChart consumes. The funnel
// detail uses the conversion-rate metric so the bars compare *conversion per
// group* rather than raw volume. Pure → unit-tested without ECharts.
// =============================================================================

import type { BreakdownResponse, MetricName } from '@/api/types'

/** Dimensions offered in the funnel breakdown selector (screens.md §3.2). */
export const FUNNEL_BREAKDOWN_DIMENSIONS = ['device_type', 'country', 'referrer_domain'] as const
export type FunnelBreakdownDimension = (typeof FUNNEL_BREAKDOWN_DIMENSIONS)[number]

export interface FunnelBarData {
  categories: string[]
  values: number[]
}

interface BuildOptions {
  metric?: MetricName
  /** Cap the number of bars; the rest are dropped (already sorted by backend). */
  limit?: number
  /** Optional display relabeller, e.g. ISO country code → localized name. */
  labelFor?: (value: string) => string
}

/**
 * Build bar-chart data from a funnel breakdown. Rows missing the chosen metric
 * fall back to 0 so a partial response still renders aligned bars. Percentage
 * metrics are passed through unscaled (0–100); the chart formatter handles unit.
 */
export function buildFunnelBreakdown(
  response: BreakdownResponse | null | undefined,
  options: BuildOptions = {},
): FunnelBarData {
  const metric = options.metric ?? 'conversion_rate'
  const rows = response?.rows ?? []
  const limited = typeof options.limit === 'number' ? rows.slice(0, options.limit) : rows
  return {
    categories: limited.map((row) =>
      options.labelFor ? options.labelFor(row.value) : row.value,
    ),
    values: limited.map((row) => row.metrics[metric] ?? 0),
  }
}
