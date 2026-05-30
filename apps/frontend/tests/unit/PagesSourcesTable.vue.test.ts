// =============================================================================
// PagesSourcesTable.vue — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Named after the component under test (.vue) so the file does not collide with
// the camel-case adapter test (pagesSourcesTable.test.ts) on case-insensitive
// file systems, which would abort vue-tsc with TS1149.
// Coverage target: ≥90% lines + branches for PagesSourcesTable.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import PagesSourcesTable from '@/components/pagessources/PagesSourcesTable.vue'
import type { PagesSourcesRow } from '@/components/pagessources/pagesSourcesTable'
import { testI18n } from '../setup'

function row(value: string, visitors: number, barPct = 100): PagesSourcesRow {
  return {
    value,
    barPct,
    metrics: {
      visitors,
      pageviews: visitors * 2,
      sessions: 0,
      bounce_rate: 4210,
      avg_duration: 84,
      events: 0,
      conversion_rate: 0,
    },
  }
}

const rows = [row('/pricing', 12043, 100), row('/docs', 9812, 81)]

function renderTable(props: Record<string, unknown> = {}) {
  return render(PagesSourcesTable, {
    props: {
      rows,
      tab: 'pages',
      dimensionHeader: 'Page',
      sort: { key: 'visitors', direction: 'desc' },
      page: 1,
      pageSize: 25,
      pageCount: 3,
      filteredCount: 60,
      totalCount: 60,
      ...props,
    },
    global: { plugins: [testI18n] },
  })
}

describe('PagesSourcesTable — rendering', () => {
  it('renders a row per data item with its dimension value', () => {
    renderTable()
    expect(screen.getByText('/pricing')).toBeInTheDocument()
    expect(screen.getByText('/docs')).toBeInTheDocument()
  })

  it('formats the visitor counts with the locale integer format', () => {
    renderTable()
    expect(screen.getByText('12,043')).toBeInTheDocument()
  })

  it('formats the bounce-rate percent and duration columns', () => {
    renderTable()
    // bounce_rate 4210 → 42.10% (value/100); avg_duration 84s → 1m 24s
    expect(screen.getAllByText('42.1%').length).toBeGreaterThan(0)
    expect(screen.getAllByText('1m 24s').length).toBeGreaterThan(0)
  })

  it('renders one inline ProgressBar per row', () => {
    const { container } = renderTable()
    expect(container.querySelectorAll('[role="progressbar"]')).toHaveLength(2)
  })

  it('renders a column header per metric plus the dimension header', () => {
    renderTable()
    // Exact match so the "Page" dimension header is not conflated with the
    // "Page views" metric header.
    expect(screen.getByRole('button', { name: 'Page' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Visitors/ })).toBeInTheDocument()
  })
})

describe('PagesSourcesTable — direct / none labels', () => {
  it('shows the direct placeholder for an empty source value', () => {
    renderTable({ rows: [row('', 10)], tab: 'sources' })
    expect(screen.getByText('Direct / none')).toBeInTheDocument()
  })

  it('shows the UTM none placeholder when utm is set', () => {
    renderTable({ rows: [row('', 10)], utm: true })
    expect(screen.getByText('(none)')).toBeInTheDocument()
  })
})

describe('PagesSourcesTable — interaction', () => {
  it('emits update:sort when a metric header is clicked', async () => {
    const { emitted } = renderTable()
    await fireEvent.click(screen.getByRole('button', { name: /Page views/ }))
    expect(emitted()['update:sort'][0]).toEqual([{ key: 'pageviews', direction: 'desc' }])
  })

  it('emits rowClick when a row is clicked', async () => {
    const { emitted } = renderTable()
    await fireEvent.click(screen.getByText('/pricing'))
    expect(emitted().rowClick).toBeTruthy()
  })

  it('emits rowClick when a row is activated with the keyboard', async () => {
    const { emitted } = renderTable()
    const rowEl = screen.getByText('/pricing').closest('tr')
    expect(rowEl).toBeTruthy()
    expect(rowEl).toHaveAttribute('tabindex', '0')
    await fireEvent.keyDown(rowEl as Element, { key: 'Enter' })
    expect(emitted().rowClick).toBeTruthy()
  })
})

describe('PagesSourcesTable — pagination', () => {
  it('shows the result range and page indicator', () => {
    renderTable()
    expect(screen.getByText(/Showing 1–25 of 60/)).toBeInTheDocument()
    expect(screen.getByText(/Page 1 of 3/)).toBeInTheDocument()
  })

  it('disables previous on the first page and emits next', async () => {
    const { emitted } = renderTable()
    expect(screen.getByRole('button', { name: 'Previous' })).toHaveAttribute('aria-disabled', 'true')
    await fireEvent.click(screen.getByRole('button', { name: 'Next' }))
    expect(emitted()['update:page'][0]).toEqual([2])
  })

  it('disables next on the last page', () => {
    renderTable({ page: 3 })
    expect(screen.getByRole('button', { name: 'Next' })).toHaveAttribute('aria-disabled', 'true')
  })
})

describe('PagesSourcesTable — states', () => {
  it('renders skeleton rows while loading', () => {
    const { container } = renderTable({ loading: true })
    expect(container.querySelectorAll('.sf-skeleton').length).toBeGreaterThan(0)
  })

  it('renders an empty state when there are no rows', () => {
    renderTable({ rows: [], filteredCount: 0, totalCount: 0, pageCount: 1 })
    expect(screen.getByText('No data for this view')).toBeInTheDocument()
  })

  it('renders an error state with retry and emits retry', async () => {
    const { emitted } = renderTable({ error: true })
    expect(screen.getByText('Failed to load')).toBeInTheDocument()
    await fireEvent.click(screen.getByRole('button', { name: /Retry/ }))
    expect(emitted().retry).toBeTruthy()
  })
})
