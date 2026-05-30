// =============================================================================
// overviewExport — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for overviewExport.ts
// =============================================================================

import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  csvField,
  buildOverviewCsv,
  downloadCsv,
  type OverviewExportInput,
} from '@/components/overview/overviewExport'
import type { BreakdownListRow } from '@/components/overview/breakdownTable'

function row(value: string, count: number, sharePct: number): BreakdownListRow {
  return { value, label: value, count, sharePct, barPct: 100 }
}

describe('csvField', () => {
  it('leaves a plain value untouched', () => {
    expect(csvField('pricing')).toBe('pricing')
  })

  it('quotes a value containing a comma', () => {
    expect(csvField('a,b')).toBe('"a,b"')
  })

  it('escapes embedded double quotes by doubling them', () => {
    expect(csvField('say "hi"')).toBe('"say ""hi"""')
  })

  it('quotes a value containing a newline', () => {
    expect(csvField('line1\nline2')).toBe('"line1\nline2"')
  })

  it('stringifies numbers', () => {
    expect(csvField(1234)).toBe('1234')
  })
})

describe('buildOverviewCsv', () => {
  const input: OverviewExportInput = {
    metricsTitle: 'Overview',
    metrics: [
      { label: 'Visitors', value: '61,412' },
      { label: 'Bounce rate', value: '42.1%' },
    ],
    sections: [
      {
        title: 'Top pages',
        dimensionHeader: 'Page',
        metricHeader: 'Visitors',
        rows: [row('/pricing', 12043, 45.2), row('/docs', 9812, 36.8)],
      },
    ],
  }

  it('leads with the KPI block', () => {
    const csv = buildOverviewCsv(input)
    const lines = csv.split('\r\n')
    expect(lines[0]).toBe('Overview')
    expect(lines[1]).toBe('Metric,Value')
    expect(lines[2]).toBe('"Visitors","61,412"')
  })

  it('separates each section with a blank line and its own header', () => {
    const csv = buildOverviewCsv(input)
    const lines = csv.split('\r\n')
    expect(lines).toContain('')
    expect(lines).toContain('Top pages')
    expect(lines).toContain('Page,Visitors,Share %')
  })

  it('writes the dimension, count and one-decimal share for each row', () => {
    const csv = buildOverviewCsv(input)
    expect(csv).toContain('/pricing,12043,45.2')
    expect(csv).toContain('/docs,9812,36.8')
  })

  it('handles input with no breakdown sections', () => {
    const csv = buildOverviewCsv({ ...input, sections: [] })
    expect(csv.split('\r\n')[0]).toBe('Overview')
    expect(csv).not.toContain('Share %')
  })
})

describe('downloadCsv', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('creates an anchor, clicks it, and revokes the object URL', () => {
    const createObjectURL = vi.fn(() => 'blob:mock')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    const ok = downloadCsv('overview.csv', 'a,b')

    expect(ok).toBe(true)
    expect(createObjectURL).toHaveBeenCalledOnce()
    expect(clickSpy).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock')
    vi.unstubAllGlobals()
  })

  it('returns false when createObjectURL is unavailable', () => {
    vi.stubGlobal('URL', {})
    expect(downloadCsv('x.csv', 'a')).toBe(false)
    vi.unstubAllGlobals()
  })
})
