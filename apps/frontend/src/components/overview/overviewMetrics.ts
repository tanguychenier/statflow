// =============================================================================
// Overview — metric card view-model builder
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Turns an AggregateMetricsResponse (plus the matching time series for the
// sparklines) into the five MetricCard view-models the Overview header renders.
// All formatting and trend semantics live here — pure, deterministic, and unit
// tested — so OverviewView.vue stays a thin orchestrator.
// =============================================================================

import type {
  AggregateMetricsResponse,
  MetricName,
  TimeSeriesResponse,
} from '@/api/types'
import type { Trend, TrendDirection } from '@/types'

/** A metric whose value goes *down* when things improve (lower bounce = better). */
const INVERTED_METRICS: ReadonlySet<MetricName> = new Set<MetricName>(['bounce_rate'])

/** Metrics rendered as percentages rather than raw counts. */
const PERCENT_METRICS: ReadonlySet<MetricName> = new Set<MetricName>([
  'bounce_rate',
  'conversion_rate',
])

/** Metrics rendered as a "Xm YYs" duration. */
const DURATION_METRICS: ReadonlySet<MetricName> = new Set<MetricName>(['avg_duration'])

/** The five KPIs shown on the Overview header, in display order. */
export const OVERVIEW_METRICS: readonly MetricName[] = [
  'visitors',
  'pageviews',
  'sessions',
  'bounce_rate',
  'avg_duration',
] as const

/** i18n key for each metric's card title. */
const TITLE_KEYS: Record<MetricName, string> = {
  visitors: 'overview.metrics.users',
  pageviews: 'overview.metrics.pageviews',
  sessions: 'overview.metrics.sessions',
  bounce_rate: 'overview.metrics.bounceRate',
  avg_duration: 'overview.metrics.avgDuration',
  events: 'overview.metrics.events',
  conversion_rate: 'overview.metrics.conversionRate',
}

/** Resolve the i18n title key for any metric (not only the displayed five). */
export function metricTitleKey(metric: MetricName): string {
  return TITLE_KEYS[metric]
}

export interface OverviewMetricCard {
  /** Stable key for v-for and detail-panel selection. */
  id: MetricName
  /** i18n key for the card title. */
  titleKey: string
  /** Formatted display value (e.g. "61,412", "42.1%", "2m 04s"). */
  value: string
  /** Trend vs. the comparison period, or undefined when comparison is off. */
  trend?: Trend
  /** Compact per-bucket series feeding the embedded sparkline. */
  sparkData: number[]
}

export interface MetricFormatters {
  /** Locale-aware integer / count formatting (n(value, 'integer')). */
  count: (n: number) => string
  /** Locale-aware percentage from a 0–100 input (n(value / 100, 'percent')). */
  percent: (n: number) => string
  /** Seconds → "2m 04s". */
  duration: (seconds: number) => string
}

function formatValue(metric: MetricName, value: number, fmt: MetricFormatters): string {
  if (value === 0) return fmt.count(0)
  if (DURATION_METRICS.has(metric)) return fmt.duration(value)
  if (PERCENT_METRICS.has(metric)) return fmt.percent(value)
  return fmt.count(value)
}

function directionOf(changePct: number): TrendDirection {
  if (changePct > 0) return 'up'
  if (changePct < 0) return 'down'
  return 'neutral'
}

/**
 * Build a Trend from a signed change percentage. `positiveIsUp` is false for
 * inverted metrics so the MetricCard colours a falling bounce rate as positive.
 * Bounce rate compares in *percentage points*, every other metric in percent —
 * the caller-supplied `changePct` already carries the right unit; we only assign
 * the colour semantics and rounded magnitude here.
 */
function buildTrend(metric: MetricName, changePct: number | null | undefined): Trend | undefined {
  if (changePct === null || changePct === undefined || Number.isNaN(changePct)) return undefined
  return {
    value: changePct,
    direction: directionOf(changePct),
    positiveIsUp: !INVERTED_METRICS.has(metric),
  }
}

/** Per-bucket values for one metric, used as the card's sparkline series. */
function sparkSeries(metric: MetricName, series: TimeSeriesResponse | null | undefined): number[] {
  if (!series) return []
  return series.series.map((bucket) => bucket.metrics[metric] ?? 0)
}

/**
 * Compose the Overview metric cards from the aggregate response and (optionally)
 * the time series that drives the sparklines. Missing metrics default to 0 so a
 * partial backend response still renders five well-formed cards.
 */
export function buildOverviewMetrics(
  aggregate: AggregateMetricsResponse | null | undefined,
  fmt: MetricFormatters,
  series?: TimeSeriesResponse | null,
): OverviewMetricCard[] {
  return OVERVIEW_METRICS.map((metric) => {
    const value = aggregate?.metrics?.[metric] ?? 0
    const changePct = aggregate?.comparison?.change_pct?.[metric]
    return {
      id: metric,
      titleKey: TITLE_KEYS[metric],
      value: formatValue(metric, value, fmt),
      trend: buildTrend(metric, changePct),
      sparkData: sparkSeries(metric, series),
    }
  })
}
