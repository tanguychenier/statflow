// =============================================================================
// RealtimeStatusPill — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for RealtimeStatusPill.vue
// =============================================================================

import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen } from '@testing-library/vue'
import RealtimeStatusPill from '@/components/realtime/RealtimeStatusPill.vue'
import { testI18n } from '../setup'

function renderPill(props: Record<string, unknown> = {}) {
  return render(RealtimeStatusPill, {
    props: { status: 'open', lastStatsAt: null, ...props },
    global: { plugins: [testI18n] },
  })
}

afterEach(() => {
  vi.useRealTimers()
})

describe('RealtimeStatusPill', () => {
  it('shows the "Live" prefix and a connecting label before any stats', () => {
    renderPill({ status: 'connecting', lastStatsAt: null })
    expect(screen.getByText(/Live/)).toBeInTheDocument()
    expect(screen.getByText(/connecting…/)).toBeInTheDocument()
  })

  it('shows a reconnecting label on error', () => {
    renderPill({ status: 'error', lastStatsAt: null })
    expect(screen.getByText(/reconnecting…/)).toBeInTheDocument()
  })

  it('renders the relative "updated Ns ago" label once stats have arrived', () => {
    vi.useFakeTimers()
    const now = Date.now()
    vi.setSystemTime(now)
    renderPill({ status: 'open', lastStatsAt: now - 2_000 })
    expect(screen.getByText(/updated/)).toBeInTheDocument()
    expect(screen.getByText(/2s ago/)).toBeInTheDocument()
  })

  it('marks the dot live when the stream is open', () => {
    const { container } = renderPill({ status: 'open', lastStatsAt: Date.now() })
    expect(container.querySelector('.status--live')).not.toBeNull()
  })

  it('marks the pill in error tone when the stream errors', () => {
    const { container } = renderPill({ status: 'error', lastStatsAt: null })
    expect(container.querySelector('.status--error')).not.toBeNull()
  })

  it('exposes a polite live region', () => {
    renderPill()
    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite')
  })
})
