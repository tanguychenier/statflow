// =============================================================================
// Overview — time-series chart adapter
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Reshapes a TimeSeriesResponse into the { categories, series } pair the
// TimeSeriesChart wants, including an optional dashed comparison overlay. Kept
// pure so the date-bucket / comparison logic is unit-tested without ECharts.
// =============================================================================

import type { MetricName, TimeSeriesResponse } from '@/api/types'
import type { SeriesInput } from '@/charts/options'

export interface OverviewChartData {
  categories: string[]
  series: SeriesInput[]
}

interface BuildOptions {
  /** Which metric to plot as the primary line. */
  metric?: MetricName
  /** Label for the primary series (already translated). */
  currentLabel: string
  /** Label for the dashed comparison overlay (already translated). */
  comparisonLabel: string
  /**
   * Comparison buckets, aligned index-for-index with the primary series. When
   * present and the same length, they render as a dashed overlay; otherwise the
   * overlay is dropped rather than misaligning the X-axis.
   */
  comparison?: TimeSeriesResponse | null
}

function valuesFor(series: TimeSeriesResponse | null | undefined, metric: MetricName): number[] {
  if (!series) return []
  return series.series.map((bucket) => bucket.metrics[metric] ?? 0)
}

/**
 * Build the chart payload. The X-axis comes from the primary response's bucket
 * timestamps; the comparison overlay is only attached when it has a matching
 * number of buckets, so the dashed line always lines up with the current line.
 */
export function buildOverviewSeries(
  primary: TimeSeriesResponse | null | undefined,
  options: BuildOptions,
): OverviewChartData {
  const metric = options.metric ?? 'visitors'
  const categories = primary?.series.map((bucket) => bucket.bucket) ?? []
  const series: SeriesInput[] = []

  if (primary) {
    series.push({ name: options.currentLabel, data: valuesFor(primary, metric) })
  }

  const compareValues = valuesFor(options.comparison, metric)
  if (compareValues.length > 0 && compareValues.length === categories.length) {
    series.push({ name: options.comparisonLabel, data: compareValues, comparison: true })
  }

  return { categories, series }
}
