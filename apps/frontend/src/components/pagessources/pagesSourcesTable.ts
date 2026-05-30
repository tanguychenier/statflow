// =============================================================================
// Pages & Sources — breakdown → table view-model (screens.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The screen fetches one BreakdownResponse per tab and renders a feature-rich
// table over it: every metric column, an inline bar sized against the leader of
// the *active* metric, client-side search, multi-direction sort and pagination.
// All of that maths lives here as pure functions so it is unit-testable without
// mounting the table — the .vue layer only wires events to these helpers.
// =============================================================================

import type { BreakdownResponse, MetricName } from '@/api/types'
import type { SortDirection } from '@/components/DataTable/types'
import { REQUESTED_METRICS } from './tabs'

export interface PagesSourcesRow {
  /** Raw dimension value (pathname, referrer domain, utm value, …). */
  value: string
  /** Every requested metric, defaulted to 0 when the API omits it. */
  metrics: Record<MetricName, number>
  /** Bar width (0–100) for the active metric, relative to the page leader. */
  barPct: number
}

export interface PageResult {
  rows: PagesSourcesRow[]
  /** Rows matching the search, before pagination — drives the pager total. */
  filteredCount: number
  /** Total rows in the breakdown, before search — shown in the result count. */
  totalCount: number
  /** 1-based page index, clamped to the available range. */
  page: number
  pageCount: number
}

export interface ShapeOptions {
  search?: string
  sortMetric: MetricName
  sortDirection: SortDirection
  page: number
  pageSize: number
}

function zeroedMetrics(): Record<MetricName, number> {
  return {
    visitors: 0,
    pageviews: 0,
    sessions: 0,
    bounce_rate: 0,
    avg_duration: 0,
    events: 0,
    conversion_rate: 0,
  }
}

/** Normalise a raw breakdown response into fully-populated metric rows. */
export function toRows(response: BreakdownResponse | null | undefined): PagesSourcesRow[] {
  return (response?.rows ?? []).map((row) => {
    const metrics = zeroedMetrics()
    for (const metric of REQUESTED_METRICS) {
      metrics[metric] = row.metrics[metric] ?? 0
    }
    return { value: row.value, metrics, barPct: 0 }
  })
}

function matchesSearch(row: PagesSourcesRow, needle: string): boolean {
  return row.value.toLowerCase().includes(needle)
}

function compareRows(
  a: PagesSourcesRow,
  b: PagesSourcesRow,
  metric: MetricName,
  direction: SortDirection,
): number {
  const delta = a.metrics[metric] - b.metrics[metric]
  if (delta !== 0) return direction === 'asc' ? delta : -delta
  // Stable tie-break on the dimension value so equal metrics keep a deterministic order.
  return a.value.localeCompare(b.value)
}

/**
 * Apply search → sort → pagination to the rows and decorate the visible page
 * with a leader-relative bar for the active metric. The bar is scaled against
 * the leader of the *filtered* set so it stays meaningful while searching.
 */
export function shapeRows(
  rows: PagesSourcesRow[],
  options: ShapeOptions,
): PageResult {
  const needle = options.search?.trim().toLowerCase() ?? ''
  const filtered = needle ? rows.filter((row) => matchesSearch(row, needle)) : rows

  const sorted = [...filtered].sort((a, b) =>
    compareRows(a, b, options.sortMetric, options.sortDirection),
  )

  const pageSize = Math.max(1, options.pageSize)
  const pageCount = Math.max(1, Math.ceil(sorted.length / pageSize))
  const page = Math.min(Math.max(1, options.page), pageCount)
  const start = (page - 1) * pageSize
  const visible = sorted.slice(start, start + pageSize)

  const max = filtered.reduce((peak, row) => Math.max(peak, row.metrics[options.sortMetric]), 0)
  const decorated = visible.map((row) => ({
    ...row,
    barPct: max > 0 ? (row.metrics[options.sortMetric] / max) * 100 : 0,
  }))

  return {
    rows: decorated,
    filteredCount: filtered.length,
    totalCount: rows.length,
    page,
    pageCount,
  }
}

export interface CsvColumn {
  metric: MetricName
  header: string
}

/** RFC 4180 field escaping (mirrors the Overview export rules). */
export function csvField(value: string | number): string {
  const str = String(value)
  return /[",\n\r]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str
}

/**
 * Serialise the *full filtered+sorted* result (not just the visible page) to a
 * CSV document: a dimension column followed by every metric column.
 */
export function buildTableCsv(
  rows: PagesSourcesRow[],
  dimensionHeader: string,
  columns: CsvColumn[],
  options: Omit<ShapeOptions, 'page' | 'pageSize'>,
): string {
  const needle = options.search?.trim().toLowerCase() ?? ''
  const filtered = needle ? rows.filter((row) => matchesSearch(row, needle)) : rows
  const sorted = [...filtered].sort((a, b) =>
    compareRows(a, b, options.sortMetric, options.sortDirection),
  )

  const header = [dimensionHeader, ...columns.map((column) => column.header)]
  const lines = [header.map(csvField).join(',')]
  for (const row of sorted) {
    const cells = [csvField(row.value), ...columns.map((column) => csvField(row.metrics[column.metric]))]
    lines.push(cells.join(','))
  }
  return lines.join('\r\n')
}

/**
 * Trigger a browser download of `content` as `filename`. No-ops outside a DOM
 * (returns false) so unit tests and SSR can call it safely.
 */
export function downloadCsv(filename: string, content: string): boolean {
  if (typeof document === 'undefined' || typeof URL.createObjectURL !== 'function') {
    return false
  }
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
  return true
}
