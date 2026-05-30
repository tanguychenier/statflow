// =============================================================================
// OverviewToolbar — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for OverviewToolbar.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import OverviewToolbar from '@/components/overview/OverviewToolbar.vue'
import { testI18n } from '../setup'

function renderToolbar(props: Record<string, unknown> = {}) {
  return render(OverviewToolbar, {
    props: { interval: 'day', compareEnabled: false, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('OverviewToolbar — granularity', () => {
  it('renders the four granularity options', () => {
    renderToolbar()
    expect(screen.getByRole('radio', { name: 'Daily' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Weekly' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Hourly' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Monthly' })).toBeInTheDocument()
  })

  it('marks the active interval as checked', () => {
    renderToolbar({ interval: 'week' })
    expect(screen.getByRole('radio', { name: 'Weekly' })).toHaveAttribute('aria-checked', 'true')
  })

  it('emits update:interval with the union value when a segment is chosen', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.click(screen.getByRole('radio', { name: 'Weekly' }))
    expect(emitted()['update:interval'][0]).toEqual(['week'])
  })
})

describe('OverviewToolbar — compare toggle', () => {
  it('reflects the compareEnabled prop on the switch', () => {
    renderToolbar({ compareEnabled: true })
    const sw = screen.getByRole('switch')
    expect(sw).toHaveAttribute('aria-checked', 'true')
  })

  it('emits update:compare when toggled', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.click(screen.getByRole('switch'))
    expect(emitted()['update:compare']).toBeTruthy()
    expect(emitted()['update:compare'][0]).toEqual([true])
  })
})

describe('OverviewToolbar — export', () => {
  it('emits export when the export button is clicked', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.click(screen.getByRole('button', { name: /Export the dashboard as CSV/i }))
    expect(emitted().export).toBeTruthy()
  })

  it('disables the export button while exporting', () => {
    renderToolbar({ exporting: true })
    const button = screen.getByRole('button', { name: /Export the dashboard as CSV/i })
    expect(button).toHaveAttribute('data-loading', 'true')
  })
})
