// =============================================================================
// FunnelListItem — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import FunnelListItem from '@/components/funnels/FunnelListItem.vue'
import type { FunnelListEntry } from '@/components/funnels/funnelStats'
import type { Funnel } from '@/api/types'
import { testI18n } from '../setup'

const funnel: Funnel = {
  id: 'f1',
  site_id: 's1',
  name: 'Checkout funnel',
  steps: [
    { step_index: 0, trigger_type: 'pageview', url_pattern: '/' },
    { step_index: 1, trigger_type: 'pageview', url_pattern: '/buy' },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: new Date(Date.now() - 3 * 86_400_000).toISOString(),
}

function renderItem(entry: FunnelListEntry) {
  return render(FunnelListItem, { props: { entry }, global: { plugins: [testI18n] } })
}

const queriedEntry: FunnelListEntry = { funnel, stepCount: 2, overallConversionPct: 12.3 }
const unqueriedEntry: FunnelListEntry = { funnel, stepCount: 2, overallConversionPct: null }

describe('FunnelListItem', () => {
  it('renders the funnel name and step count', () => {
    renderItem(queriedEntry)
    expect(screen.getByText('Checkout funnel')).toBeInTheDocument()
    expect(screen.getByText(/2 steps/)).toBeInTheDocument()
  })

  it('shows the conversion once queried', () => {
    renderItem(queriedEntry)
    expect(screen.getByText(/12\.3%/)).toBeInTheDocument()
  })

  it('shows a dash until queried', () => {
    renderItem(unqueriedEntry)
    expect(screen.getByText(/—/)).toBeInTheDocument()
  })

  it('emits open when the funnel name is activated', async () => {
    const { emitted } = renderItem(queriedEntry)
    await fireEvent.click(screen.getByRole('button', { name: /Open funnel/i }))
    expect(emitted().open).toBeTruthy()
  })

  it('emits edit without opening the card', async () => {
    const { emitted } = renderItem(queriedEntry)
    await fireEvent.click(screen.getByRole('button', { name: 'Edit funnel' }))
    expect(emitted().edit).toBeTruthy()
    expect(emitted().open).toBeFalsy()
  })

  it('emits remove without opening the card', async () => {
    const { emitted } = renderItem(queriedEntry)
    await fireEvent.click(screen.getByRole('button', { name: 'Delete funnel' }))
    expect(emitted().remove).toBeTruthy()
    expect(emitted().open).toBeFalsy()
  })
})
