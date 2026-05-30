// =============================================================================
// RealtimeBreakdownList — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for RealtimeBreakdownList.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import RealtimeBreakdownList from '@/components/realtime/RealtimeBreakdownList.vue'
import type { RankedRow } from '@/components/realtime/realtimeModel'
import { testI18n } from '../setup'

const rows: RankedRow[] = [
  { key: '/pricing', label: '/pricing', value: 82, barPct: 100 },
  { key: '/', label: '/', value: 61, barPct: 74 },
]

function renderList(props: Record<string, unknown> = {}) {
  return render(RealtimeBreakdownList, {
    props: { title: 'Top pages', rows, metricLabel: 'Active users', ...props },
    global: { plugins: [testI18n] },
  })
}

describe('RealtimeBreakdownList', () => {
  it('renders the title and ranked rows with formatted values', () => {
    renderList()
    expect(screen.getByText('Top pages')).toBeInTheDocument()
    expect(screen.getByText('/pricing')).toBeInTheDocument()
    expect(screen.getByText('82')).toBeInTheDocument()
  })

  it('renders inline progress bars with the row bar percentage', () => {
    renderList()
    const bars = screen.getAllByRole('progressbar')
    expect(bars[0]).toHaveAttribute('aria-valuenow', '100')
  })

  it('renders the leading glyph when leadingFor is provided', () => {
    renderList({ leadingFor: (row: RankedRow) => `[${row.key}]` })
    expect(screen.getByText('[/pricing]')).toBeInTheDocument()
  })

  it('shows skeletons while loading and hides rows', () => {
    renderList({ loading: true })
    expect(screen.queryByText('/pricing')).not.toBeInTheDocument()
  })

  it('shows an error state with a retry action that emits retry', async () => {
    const { emitted } = renderList({ error: true, rows: [] })
    expect(screen.getByText('Live stream interrupted')).toBeInTheDocument()
    await fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(emitted().retry).toBeTruthy()
  })

  it('shows the empty state when there are no rows', () => {
    renderList({ rows: [] })
    expect(screen.getByText('No data available')).toBeInTheDocument()
  })
})
