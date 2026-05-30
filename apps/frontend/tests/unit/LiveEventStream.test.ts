// =============================================================================
// LiveEventStream — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for LiveEventStream.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import LiveEventStream from '@/components/realtime/LiveEventStream.vue'
import type { LiveEventRow } from '@/components/realtime/realtimeModel'
import { testI18n } from '../setup'

const rows: LiveEventRow[] = [
  {
    id: 'a',
    timestamp: '2026-05-29T12:04:38Z',
    eventName: 'conversion',
    pathname: '/checkout',
    source: 'google.com',
    country: 'FR',
    device: 'desktop',
    browser: 'Firefox',
  },
  {
    id: 'b',
    timestamp: '2026-05-29T12:04:37Z',
    eventName: 'page_view',
    pathname: '/docs',
    source: 'direct',
    country: 'GB',
    device: 'mobile',
    browser: 'Safari',
  },
]

function renderStream(props: Record<string, unknown> = {}) {
  return render(LiveEventStream, {
    props: { rows, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('LiveEventStream', () => {
  it('renders the heading and one row per event', () => {
    renderStream()
    expect(screen.getByText('Live event stream')).toBeInTheDocument()
    expect(screen.getByText('/checkout')).toBeInTheDocument()
    expect(screen.getByText('/docs')).toBeInTheDocument()
  })

  it('shows event-name badges and the clock time', () => {
    renderStream()
    expect(screen.getByText('conversion')).toBeInTheDocument()
    expect(screen.getByText('page_view')).toBeInTheDocument()
    // Clock is HH:mm:ss derived from the timestamp.
    expect(screen.getAllByText(/\d{2}:\d{2}:\d{2}/).length).toBeGreaterThan(0)
  })

  it('renders the environment label (browser · device)', () => {
    renderStream()
    expect(screen.getByText('Firefox · desktop')).toBeInTheDocument()
  })

  it('emits toggle-pause when the pause button is clicked', async () => {
    const { emitted } = renderStream()
    await fireEvent.click(screen.getByRole('button', { name: /Pause/i }))
    expect(emitted()['toggle-pause']).toBeTruthy()
  })

  it('switches the button label to Resume and shows the buffered badge when paused', () => {
    renderStream({ paused: true, bufferedCount: 3 })
    expect(screen.getByRole('button', { name: /Resume/i })).toBeInTheDocument()
    expect(screen.getByText('3 new events')).toBeInTheDocument()
  })

  it('does not show the buffered badge when paused with zero new events', () => {
    renderStream({ paused: true, bufferedCount: 0 })
    expect(screen.queryByText(/new event/i)).not.toBeInTheDocument()
  })

  it('shows a connecting message while loading with no rows', () => {
    renderStream({ rows: [], loading: true })
    expect(screen.getByText('Connecting to the live stream…')).toBeInTheDocument()
  })

  it('shows the empty state with no rows once loaded', () => {
    renderStream({ rows: [], loading: false })
    expect(screen.getByText('No live activity yet')).toBeInTheDocument()
  })

  it('exposes the live region for assistive tech', () => {
    renderStream()
    expect(screen.getByRole('log')).toHaveAttribute('aria-live', 'polite')
  })
})
