// =============================================================================
// Overview — client-side CSV export
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The Export button (screens.md §1.2) downloads everything currently on screen
// as a single CSV: the KPI row plus each breakdown list. The serialisation is a
// pure function so the quoting/escaping rules are unit tested; the download
// helper is a thin DOM wrapper guarded for non-browser (SSR/test) contexts.
// =============================================================================

import type { BreakdownListRow } from './breakdownTable'

export interface ExportMetric {
  label: string
  value: string
}

export interface ExportSection {
  /** Section heading written as a CSV comment-style title row. */
  title: string
  /** Column header for the dimension (e.g. "Page", "Country"). */
  dimensionHeader: string
  /** Column header for the metric (e.g. "Visitors"). */
  metricHeader: string
  rows: BreakdownListRow[]
}

export interface OverviewExportInput {
  metricsTitle: string
  metrics: ExportMetric[]
  sections: ExportSection[]
}

/** RFC 4180 field escaping: quote when the value holds a comma, quote or newline. */
export function csvField(value: string | number): string {
  const str = String(value)
  if (/[",\n\r]/.test(str)) {
    return `"${str.replace(/"/g, '""')}"`
  }
  return str
}

/** Always-quoted CSV field — used for human-readable label/value pairs. */
function csvQuoted(value: string | number): string {
  const str = String(value)
  return `"${str.replace(/"/g, '""')}"`
}

function csvRow(fields: Array<string | number>): string {
  return fields.map(csvField).join(',')
}

/**
 * Serialise the visible Overview data to a CSV document. The KPI block leads,
 * then a blank line separates each breakdown section with its own header row.
 */
export function buildOverviewCsv(input: OverviewExportInput): string {
  const lines: string[] = []

  lines.push(csvRow([input.metricsTitle]))
  lines.push(csvRow(['Metric', 'Value']))
  for (const metric of input.metrics) {
    lines.push(`${csvQuoted(metric.label)},${csvQuoted(metric.value)}`)
  }

  for (const section of input.sections) {
    lines.push('')
    lines.push(csvRow([section.title]))
    lines.push(csvRow([section.dimensionHeader, section.metricHeader, 'Share %']))
    for (const row of section.rows) {
      lines.push(csvRow([row.label, row.count, row.sharePct.toFixed(1)]))
    }
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
