// =============================================================================
// Overview — breakdown → ranked list adapter
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The breakdown cards (Top pages / Sources / Devices / Countries) all render the
// same shape: a label, a primary metric value, its share of the visible total,
// and a bar sized against the leader. This adapter produces that view-model from
// a BreakdownResponse, keeping the ranking/share/percentage maths testable.
// =============================================================================

import type { BreakdownResponse, MetricName } from '@/api/types'

export interface BreakdownListRow {
  /** Raw dimension value (pathname, country code, device_type, …). */
  value: string
  /** Display label (defaults to `value`; callers may map codes → names). */
  label: string
  /** Primary metric count for the row. */
  count: number
  /** Share of the visible page's total, 0–100. */
  sharePct: number
  /** Bar width relative to the top row, 0–100. */
  barPct: number
}

interface BuildOptions {
  /** Which metric column drives the ranking and bars (defaults to visitors). */
  metric?: MetricName
  /** Truncate to the top N rows after sorting (omit to keep all). */
  limit?: number
  /** Map a raw value to a display label (e.g. country code → name). */
  labelFor?: (value: string) => string
}

/**
 * Rank breakdown rows by the chosen metric (descending) and decorate each with
 * its share of the visible total and a leader-relative bar width. Returns an
 * empty array for a null/empty response so the caller can show an empty state.
 */
export function buildBreakdownRows(
  response: BreakdownResponse | null | undefined,
  options: BuildOptions = {},
): BreakdownListRow[] {
  const metric = options.metric ?? 'visitors'
  const rows = response?.rows ?? []
  if (rows.length === 0) return []

  const counted = rows.map((row) => ({ value: row.value, count: row.metrics[metric] ?? 0 }))
  counted.sort((a, b) => b.count - a.count)

  const limited =
    options.limit !== undefined && options.limit >= 0 ? counted.slice(0, options.limit) : counted

  const total = counted.reduce((sum, row) => sum + row.count, 0)
  const max = limited.reduce((peak, row) => Math.max(peak, row.count), 0)

  return limited.map((row) => ({
    value: row.value,
    label: options.labelFor ? options.labelFor(row.value) : row.value,
    count: row.count,
    sharePct: total > 0 ? (row.count / total) * 100 : 0,
    barPct: max > 0 ? (row.count / max) * 100 : 0,
  }))
}

/** Map a country breakdown to the { name, value } pairs the GeoMap consumes. */
export function buildGeoData(
  response: BreakdownResponse | null | undefined,
  nameFor: (code: string) => string,
  metric: MetricName = 'visitors',
): Array<{ name: string; value: number }> {
  return (response?.rows ?? []).map((row) => ({
    name: nameFor(row.value),
    value: row.metrics[metric] ?? 0,
  }))
}

/** Map a device breakdown to the { name, value } slices the DonutChart consumes. */
export function buildDonutSlices(
  response: BreakdownResponse | null | undefined,
  labelFor: (value: string) => string,
  metric: MetricName = 'visitors',
): Array<{ name: string; value: number }> {
  return (response?.rows ?? []).map((row) => ({
    name: labelFor(row.value),
    value: row.metrics[metric] ?? 0,
  }))
}
