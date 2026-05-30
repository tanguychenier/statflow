// =============================================================================
// BreakdownPanel — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for BreakdownPanel.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { defineComponent } from 'vue'
import BreakdownPanel from '@/components/overview/BreakdownPanel.vue'
import type { BreakdownListRow } from '@/components/overview/breakdownTable'
import { testI18n } from '../setup'

// RouterLink is not installed in the unit harness; render it as a plain anchor.
const RouterLinkStub = defineComponent({
  name: 'RouterLink',
  props: { to: { type: String, required: true } },
  template: '<a :href="to"><slot /></a>',
})

const rows: BreakdownListRow[] = [
  { value: '/pricing', label: '/pricing', count: 12043, sharePct: 45.2, barPct: 100 },
  { value: '/docs', label: '/docs', count: 9812, sharePct: 36.8, barPct: 81 },
]

const countryRows: BreakdownListRow[] = [
  { value: 'FR', label: 'France', count: 5000, sharePct: 60, barPct: 100 },
  { value: 'US', label: 'United States of America', count: 3000, sharePct: 40, barPct: 60 },
]

function renderPanel(props: Record<string, unknown> = {}) {
  return render(BreakdownPanel, {
    props: { title: 'Top pages', rows, metricLabel: 'Visitors', ...props },
    global: { plugins: [testI18n], components: { RouterLink: RouterLinkStub } },
  })
}

describe('BreakdownPanel — loaded state', () => {
  it('renders the title', () => {
    renderPanel()
    expect(screen.getByText('Top pages')).toBeInTheDocument()
  })

  it('renders a row per data item with its label and count', () => {
    renderPanel()
    expect(screen.getByText('/pricing')).toBeInTheDocument()
    expect(screen.getByText('/docs')).toBeInTheDocument()
    expect(screen.getByText('12,043')).toBeInTheDocument()
  })

  it('renders one ProgressBar per row', () => {
    const { container } = renderPanel()
    expect(container.querySelectorAll('[role="progressbar"]')).toHaveLength(2)
  })

  it('emits selectRow with the raw value when a row is clicked', async () => {
    const { emitted } = renderPanel()
    await fireEvent.click(screen.getByText('/pricing'))
    expect(emitted().selectRow[0]).toEqual(['/pricing'])
  })
})

describe('BreakdownPanel — flags', () => {
  it('renders a flag glyph for country rows when showFlag is set', () => {
    renderPanel({ rows: countryRows, showFlag: true })
    expect(screen.getByText('\u{1F1EB}\u{1F1F7}')).toBeInTheDocument()
  })

  it('omits flags by default', () => {
    renderPanel({ rows: countryRows })
    expect(screen.queryByText('\u{1F1EB}\u{1F1F7}')).not.toBeInTheDocument()
  })
})

describe('BreakdownPanel — view all', () => {
  it('shows a "View all" link when viewAllTo is provided and rows exist', () => {
    renderPanel({ viewAllTo: '/pages-sources' })
    const link = screen.getByText(/View all/i).closest('a')
    expect(link).toHaveAttribute('href', '/pages-sources')
  })

  it('hides the "View all" link when viewAllTo is omitted', () => {
    renderPanel()
    expect(screen.queryByText(/View all/i)).not.toBeInTheDocument()
  })

  it('hides the "View all" link while loading', () => {
    renderPanel({ viewAllTo: '/pages-sources', loading: true })
    expect(screen.queryByText(/View all/i)).not.toBeInTheDocument()
  })
})

describe('BreakdownPanel — non-loaded states', () => {
  it('renders skeleton placeholders while loading', () => {
    const { container } = renderPanel({ loading: true })
    expect(container.querySelectorAll('.sf-skeleton').length).toBeGreaterThan(0)
    expect(screen.queryByText('/pricing')).not.toBeInTheDocument()
  })

  it('renders an empty state when there are no rows', () => {
    renderPanel({ rows: [] })
    expect(screen.getByText('No data available')).toBeInTheDocument()
  })

  it('renders an error state with a retry button and emits retry', async () => {
    const { emitted } = renderPanel({ error: true })
    expect(screen.getByText('Failed to load')).toBeInTheDocument()
    await fireEvent.click(screen.getByRole('button', { name: /Retry/i }))
    expect(emitted().retry).toBeTruthy()
  })

  it('does not render rows while in error state', () => {
    renderPanel({ error: true })
    expect(screen.queryByText('/pricing')).not.toBeInTheDocument()
  })
})
