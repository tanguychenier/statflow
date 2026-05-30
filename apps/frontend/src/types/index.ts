// =============================================================================
// Statflow Dashboard — Shared Type Definitions
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

/** Trend direction for a metric compared to the previous period. */
export type TrendDirection = 'up' | 'down' | 'neutral'

/** A numeric trend value with direction context. */
export interface Trend {
  /** Raw change value (e.g. 12.4 for +12.4%) */
  value: number
  /** Whether the trend direction is positive (up = positive for sessions, down = positive for bounce rate) */
  direction: TrendDirection
  /** Whether an upward trend is semantically positive for this metric */
  positiveIsUp: boolean
}

/** Time series data point. */
export interface DataPoint {
  timestamp: string
  value: number
}

/** Supported locale codes. */
export type SupportedLocale = 'en' | 'fr'

/** Application theme. */
export type Theme = 'light' | 'dark'

/** Metric card data shape. */
export interface MetricCardData {
  /** Unique identifier for this metric */
  id: string
  /** i18n translation key for the metric title */
  titleKey: string
  /** Formatted display value (e.g. "84,203" or "2m 04s") */
  value: string
  /** Raw numeric value for accessibility announcements */
  rawValue: number
  /** Trend vs. comparison period */
  trend: Trend
  /** Compact time series for the sparkline (64px tall) */
  sparkData: number[]
}
