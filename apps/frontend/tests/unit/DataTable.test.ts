// =============================================================================
// DataTable — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import DataTable from '@/components/DataTable/DataTable.vue'
import type { ColumnDef } from '@/components/DataTable/types'
import { testI18n } from '../setup'

interface PageRow extends Record<string, unknown> {
  path: string
  sessions: number
}

const columns: ColumnDef<Record<string, unknown>>[] = [
  { key: 'path', header: 'Page', sortable: true },
  { key: 'sessions', header: 'Sessions', numeric: true, sortable: true },
]

const rows: PageRow[] = [
  { path: '/pricing', sessions: 12043 },
  { path: '/docs', sessions: 9812 },
]

function renderTable(props: Record<string, unknown>) {
  return render(DataTable, {
    props: { columns, rows, rowKey: 'path', ...props },
    global: { plugins: [testI18n] },
  })
}

describe('DataTable', () => {
  it('renders a row per data item', () => {
    renderTable({})
    expect(screen.getByText('/pricing')).toBeInTheDocument()
    expect(screen.getByText('/docs')).toBeInTheDocument()
  })

  it('renders skeleton rows while loading', () => {
    const { container } = renderTable({ loading: true })
    expect(container.querySelectorAll('.sf-skeleton').length).toBeGreaterThan(0)
    expect(screen.queryByText('/pricing')).not.toBeInTheDocument()
  })

  it('shows the empty state when there are no rows', () => {
    renderTable({ rows: [] })
    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('emits update:sort toggling desc then asc on a sortable header', async () => {
    const { emitted } = renderTable({})
    const header = screen.getByRole('button', { name: /Sessions/ })
    await fireEvent.click(header)
    expect(emitted()['update:sort'][0]).toEqual([{ key: 'sessions', direction: 'desc' }])
  })

  it('reflects the active sort direction with aria-sort', () => {
    renderTable({ sort: { key: 'sessions', direction: 'asc' } })
    const cells = screen.getAllByRole('columnheader')
    const sessionsHeader = cells.find((c) => c.textContent?.includes('Sessions'))
    expect(sessionsHeader?.getAttribute('aria-sort')).toBe('ascending')
  })

  it('renders selection checkboxes and emits update:selection', async () => {
    const { emitted } = renderTable({ selectable: true })
    const rowCheckbox = screen.getByRole('checkbox', { name: 'Select row /pricing' })
    await fireEvent.click(rowCheckbox)
    expect(emitted()['update:selection'][0]).toEqual([['/pricing']])
  })

  it('selects all rows from the header checkbox', async () => {
    const { emitted } = renderTable({ selectable: true })
    const selectAll = screen.getByRole('checkbox', { name: 'Select all rows' })
    await fireEvent.click(selectAll)
    expect(emitted()['update:selection'][0]).toEqual([['/pricing', '/docs']])
  })

  it('emits rowClick when a row is clicked', async () => {
    const { emitted } = renderTable({})
    await fireEvent.click(screen.getByText('/pricing'))
    expect(emitted().rowClick).toBeTruthy()
  })

  it('leaves rows non-focusable by default', () => {
    const { container } = renderTable({})
    const bodyRows = container.querySelectorAll('tbody tr')
    expect(bodyRows.length).toBe(2)
    bodyRows.forEach((tr) => {
      expect(tr.hasAttribute('tabindex')).toBe(false)
      expect(tr.hasAttribute('aria-label')).toBe(false)
    })
  })

  it('exposes rows as focusable, labelled controls when rowInteractive is set', () => {
    const { container } = renderTable({ rowInteractive: true })
    const bodyRows = container.querySelectorAll('tbody tr')
    bodyRows.forEach((tr) => {
      expect(tr.getAttribute('tabindex')).toBe('0')
      expect(tr.getAttribute('aria-label')).toBeTruthy()
    })
  })

  it('activates a row with Enter when rowInteractive', async () => {
    const { container, emitted } = renderTable({ rowInteractive: true })
    const firstRow = container.querySelector('tbody tr') as HTMLElement
    await fireEvent.keyDown(firstRow, { key: 'Enter' })
    expect(emitted().rowClick?.[0]).toEqual([rows[0]])
  })

  it('activates a row with Space (and prevents page scroll) when rowInteractive', async () => {
    const { container, emitted } = renderTable({ rowInteractive: true })
    const firstRow = container.querySelector('tbody tr') as HTMLElement
    const event = new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true })
    firstRow.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
    expect(emitted().rowClick?.[0]).toEqual([rows[0]])
  })

  it('ignores unrelated keys on an interactive row', async () => {
    const { container, emitted } = renderTable({ rowInteractive: true })
    const firstRow = container.querySelector('tbody tr') as HTMLElement
    await fireEvent.keyDown(firstRow, { key: 'a' })
    expect(emitted().rowClick).toBeFalsy()
  })
})
