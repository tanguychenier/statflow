// =============================================================================
// RealtimeKpiCard — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for RealtimeKpiCard.vue
// =============================================================================

import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen } from '@testing-library/vue'
import { nextTick } from 'vue'
import RealtimeKpiCard from '@/components/realtime/RealtimeKpiCard.vue'
import { testI18n } from '../setup'

function renderCard(props: Record<string, unknown> = {}) {
  return render(RealtimeKpiCard, {
    props: { label: 'Active users', value: '247', ...props },
    global: { plugins: [testI18n] },
  })
}

describe('RealtimeKpiCard', () => {
  it('renders the label and value', () => {
    renderCard()
    expect(screen.getByText('Active users')).toBeInTheDocument()
    expect(screen.getByText('247')).toBeInTheDocument()
  })

  it('exposes an accessible name combining label and value', () => {
    renderCard()
    expect(screen.getByLabelText('Active users: 247')).toBeInTheDocument()
  })

  it('renders a placeholder and busy state while loading', () => {
    const { container } = renderCard({ loading: true })
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(container.querySelector('[aria-busy="true"]')).not.toBeNull()
  })

  it('applies the mono modifier when requested', () => {
    const { container } = renderCard({ mono: true, value: '/pricing' })
    expect(container.querySelector('.kpi__value--mono')).not.toBeNull()
  })

  it('pulses the value on change when pulse is enabled', async () => {
    const { rerender, container } = renderCard({ pulse: true, value: '247' })
    await rerender({ label: 'Active users', pulse: true, value: '248' })
    // requestAnimationFrame schedules the class toggle.
    await new Promise((resolve) => requestAnimationFrame(() => resolve(null)))
    await nextTick()
    expect(container.querySelector('.kpi__value--pulse')).not.toBeNull()
  })

  it('does not pulse when pulse is disabled', async () => {
    const { rerender, container } = renderCard({ pulse: false, value: '247' })
    await rerender({ label: 'Active users', pulse: false, value: '248' })
    await new Promise((resolve) => requestAnimationFrame(() => resolve(null)))
    await nextTick()
    expect(container.querySelector('.kpi__value--pulse')).toBeNull()
  })
})

describe('RealtimeKpiCard — live announcements', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  function liveRegion(container: Element) {
    return container.querySelector('[aria-live="polite"]')
  }

  it('does not render a live region unless live is set', () => {
    const { container } = renderCard()
    expect(liveRegion(container)).toBeNull()
  })

  it('renders a polite, atomic live region carrying the first value', () => {
    const { container } = renderCard({ live: true })
    const region = liveRegion(container)
    expect(region).not.toBeNull()
    expect(region?.getAttribute('aria-atomic')).toBe('true')
    expect(region?.textContent?.trim()).toBe('Active users: 247')
  })

  it('throttles announcements to at most once per window', async () => {
    vi.useFakeTimers()
    const { rerender, container } = renderCard({ live: true, liveThrottleMs: 5_000, value: '247' })
    const region = liveRegion(container)
    expect(region?.textContent?.trim()).toBe('Active users: 247')

    // A burst of updates inside the window must not re-announce immediately.
    await rerender({ label: 'Active users', live: true, liveThrottleMs: 5_000, value: '248' })
    await rerender({ label: 'Active users', live: true, liveThrottleMs: 5_000, value: '251' })
    expect(region?.textContent?.trim()).toBe('Active users: 247')

    // After the window elapses the latest value is announced once.
    await vi.advanceTimersByTimeAsync(5_000)
    await nextTick()
    expect(region?.textContent?.trim()).toBe('Active users: 251')
  })

  it('does not announce the loading placeholder', () => {
    const { container } = renderCard({ live: true, loading: true })
    expect(liveRegion(container)?.textContent?.trim()).toBe('')
  })
})
