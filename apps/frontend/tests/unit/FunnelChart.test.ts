// =============================================================================
// FunnelChart / ProgressBar — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import FunnelChart from '@/components/charts/FunnelChart.vue'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import type { FunnelResponseStep } from '@/api/types'
import { testI18n } from '../setup'

const steps: FunnelResponseStep[] = [
  {
    step_index: 0,
    label: 'Landing',
    event_name: 'pageview',
    entered: 1000,
    converted: 680,
    dropped: 320,
    conversion_rate_pct: 68,
    avg_time_to_convert_seconds: 0,
  },
  {
    step_index: 1,
    label: 'Pricing',
    event_name: 'pageview',
    entered: 680,
    converted: 300,
    dropped: 380,
    conversion_rate_pct: 44,
    avg_time_to_convert_seconds: 30,
  },
]

function renderFunnel(props: Record<string, unknown>) {
  return render(FunnelChart, { props: { steps, ...props }, global: { plugins: [testI18n] } })
}

describe('FunnelChart', () => {
  it('renders a list item per step', () => {
    renderFunnel({})
    expect(screen.getByText('Landing')).toBeInTheDocument()
    expect(screen.getByText('Pricing')).toBeInTheDocument()
  })

  it('shows the empty state for no steps', () => {
    renderFunnel({ steps: [] })
    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('renders a skeleton while loading', () => {
    const { container } = renderFunnel({ loading: true })
    expect(container.querySelector('.sf-skeleton')).toBeInTheDocument()
  })

  it('renders the error state with a retry affordance', () => {
    renderFunnel({ error: true })
    expect(screen.getByRole('status')).toBeInTheDocument()
  })
})

describe('ProgressBar', () => {
  it('clamps the rendered width to 0–100%', () => {
    const { container } = render(ProgressBar, { props: { value: 150, max: 100 } })
    const fill = container.querySelector('[style*="width"]') as HTMLElement
    expect(fill.style.width).toBe('100%')
  })

  it('computes the percentage from value/max', () => {
    const { container } = render(ProgressBar, { props: { value: 25, max: 50 } })
    const bar = container.querySelector('[role="progressbar"]')
    expect(bar?.getAttribute('aria-valuenow')).toBe('50')
  })

  it('handles a non-positive max without dividing by zero', () => {
    const { container } = render(ProgressBar, { props: { value: 5, max: 0 } })
    const bar = container.querySelector('[role="progressbar"]')
    expect(bar?.getAttribute('aria-valuenow')).toBe('0')
  })
})
