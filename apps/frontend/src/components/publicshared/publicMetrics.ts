// =============================================================================
// Public shared dashboard — metric card view-model builder
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Shapes the public `AggregateMetricsResponse` into the KPI cards the shared
// dashboard renders (screens.md §8.1). Kept pure and deterministic so the view
// stays a thin orchestrator and the formatting/trend rules are unit-testable in
// isolation. The public endpoint only exposes aggregate metrics — no breakdowns
// or series — so this is the single shaping seam for the screen.
// =============================================================================

import type { AggregateMetricsResponse, MetricName } from '@/api/types'
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

/** KPIs shown on the public dashboard, in display order (screens.md §8.1). */
export const PUBLIC_METRICS: readonly MetricName[] = [
  'sessions',
  'visitors',
  'pageviews',
  'bounce_rate',
  'avg_duration',
] as const

/** i18n key for each metric's card title. */
const TITLE_KEYS: Partial<Record<MetricName, string>> = {
  sessions: 'publicDashboard.metrics.sessions',
  visitors: 'publicDashboard.metrics.users',
  pageviews: 'publicDashboard.metrics.pageviews',
  bounce_rate: 'publicDashboard.metrics.bounceRate',
  avg_duration: 'publicDashboard.metrics.avgDuration',
}

export interface PublicMetricCard {
  /** Stable key for v-for. */
  id: MetricName
  /** i18n key for the card title. */
  titleKey: string
  /** Formatted display value (e.g. "84,203", "42.1%", "2m 04s"). */
  value: string
  /** Trend vs. the comparison period, or undefined when comparison is off. */
  trend?: Trend
}

export interface PublicMetricFormatters {
  /** Locale-aware integer / count formatting (n(value, 'integer')). */
  count: (n: number) => string
  /** Locale-aware percentage from a 0–100 input (n(value / 100, 'percent')). */
  percent: (n: number) => string
  /** Seconds → "2m 04s". */
  duration: (seconds: number) => string
}

function formatValue(metric: MetricName, value: number, fmt: PublicMetricFormatters): string {
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
 * inverted metrics so a falling bounce rate is coloured as positive.
 */
function buildTrend(metric: MetricName, changePct: number | null | undefined): Trend | undefined {
  if (changePct === null || changePct === undefined || Number.isNaN(changePct)) return undefined
  return {
    value: changePct,
    direction: directionOf(changePct),
    positiveIsUp: !INVERTED_METRICS.has(metric),
  }
}

/**
 * Compose the public KPI cards from the aggregate response. Missing metrics
 * default to 0 so a partial backend response still renders well-formed cards.
 */
export function buildPublicMetrics(
  aggregate: AggregateMetricsResponse | null | undefined,
  fmt: PublicMetricFormatters,
): PublicMetricCard[] {
  return PUBLIC_METRICS.map((metric) => {
    const value = aggregate?.metrics?.[metric] ?? 0
    const changePct = aggregate?.comparison?.change_pct?.[metric]
    return {
      id: metric,
      titleKey: TITLE_KEYS[metric] ?? metric,
      value: formatValue(metric, value, fmt),
      trend: buildTrend(metric, changePct),
    }
  })
}
