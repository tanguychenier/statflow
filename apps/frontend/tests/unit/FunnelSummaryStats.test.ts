// =============================================================================
// FunnelSummaryStats — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import FunnelSummaryStats from '@/components/funnels/FunnelSummaryStats.vue'
import type { FunnelSummary } from '@/components/funnels/funnelStats'
import { testI18n } from '../setup'

function renderStats(summary: FunnelSummary, loading = false) {
  return render(FunnelSummaryStats, {
    props: { summary, loading },
    global: { plugins: [testI18n] },
  })
}

describe('FunnelSummaryStats', () => {
  it('formats the three figures', () => {
    renderStats({
      totalEntered: 8402,
      overallConversionPct: 12.3,
      totalTimeToConvertSeconds: 272,
    })
    expect(screen.getByText(/12\.3%/)).toBeInTheDocument()
    expect(screen.getByText(/8,402/)).toBeInTheDocument()
    expect(screen.getByText('4m 32s')).toBeInTheDocument()
  })

  it('hides the time-to-convert stat when unavailable', () => {
    renderStats({
      totalEntered: 100,
      overallConversionPct: 5,
      totalTimeToConvertSeconds: null,
    })
    expect(screen.queryByText(/Median time/)).not.toBeInTheDocument()
  })

  it('renders skeletons while loading', () => {
    const { container } = renderStats(
      { totalEntered: 0, overallConversionPct: 0, totalTimeToConvertSeconds: null },
      true,
    )
    expect(container.querySelector('.sf-skeleton')).toBeInTheDocument()
  })
})
