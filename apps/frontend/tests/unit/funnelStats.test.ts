// =============================================================================
// funnelStats — summary & per-step view-model tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  buildFunnelSummary,
  buildListEntry,
  buildStepViews,
} from '@/components/funnels/funnelStats'
import type { Funnel, FunnelResponse, FunnelResponseStep } from '@/api/types'

function step(partial: Partial<FunnelResponseStep>): FunnelResponseStep {
  return {
    step_index: 0,
    label: 'Step',
    event_name: 'pageview',
    entered: 0,
    converted: 0,
    dropped: 0,
    conversion_rate_pct: 0,
    avg_time_to_convert_seconds: 0,
    ...partial,
  }
}

const response: FunnelResponse = {
  period: { from: '2026-01-01', to: '2026-01-31' },
  conversion_window_days: 30,
  steps: [
    step({ step_index: 0, label: 'View', entered: 1000, avg_time_to_convert_seconds: 0 }),
    step({ step_index: 1, label: 'Cart', entered: 680, avg_time_to_convert_seconds: 30 }),
    step({
      step_index: 2,
      label: 'Buy',
      entered: 123,
      conversion_rate_pct: 12.3,
      avg_time_to_convert_seconds: 90,
    }),
  ],
}

describe('buildFunnelSummary', () => {
  it('reads total entered from the first step', () => {
    expect(buildFunnelSummary(response).totalEntered).toBe(1000)
  })

  it('uses the last step cumulative conversion rate when present', () => {
    expect(buildFunnelSummary(response).overallConversionPct).toBeCloseTo(12.3)
  })

  it('recomputes conversion when the cumulative field is absent', () => {
    const r: FunnelResponse = {
      ...response,
      steps: [
        step({ step_index: 0, entered: 200 }),
        step({ step_index: 1, entered: 50, converted: 50, conversion_rate_pct: undefined as never }),
      ],
    }
    expect(buildFunnelSummary(r).overallConversionPct).toBeCloseTo(25)
  })

  it('sums per-step timings, ignoring zero entries', () => {
    expect(buildFunnelSummary(response).totalTimeToConvertSeconds).toBe(120)
  })

  it('returns null timing when no step has a positive duration', () => {
    const r: FunnelResponse = {
      ...response,
      steps: [step({ entered: 10 }), step({ step_index: 1, entered: 5 })],
    }
    expect(buildFunnelSummary(r).totalTimeToConvertSeconds).toBeNull()
  })

  it('is safe for an empty / nullish response', () => {
    const summary = buildFunnelSummary(null)
    expect(summary.totalEntered).toBe(0)
    expect(summary.overallConversionPct).toBe(0)
    expect(summary.totalTimeToConvertSeconds).toBeNull()
  })

  it('reports zero conversion when nobody entered', () => {
    const r: FunnelResponse = { ...response, steps: [step({ entered: 0 })] }
    expect(buildFunnelSummary(r).overallConversionPct).toBe(0)
  })
})

describe('buildStepViews', () => {
  it('computes cumulative and step-relative percentages', () => {
    const views = buildStepViews(response.steps)
    expect(views[0].continuedPct).toBeNull()
    expect(views[0].droppedPct).toBeNull()
    expect(views[1].fromEntryPct).toBeCloseTo(68)
    expect(views[1].continuedPct).toBeCloseTo(68)
    expect(views[1].droppedPct).toBeCloseTo(32)
  })

  it('handles a zero-entry first step without dividing by zero', () => {
    const views = buildStepViews([step({ entered: 0 }), step({ step_index: 1, entered: 0 })])
    expect(views[0].fromEntryPct).toBe(0)
    expect(views[1].continuedPct).toBeNull()
  })

  it('returns an empty array for no steps', () => {
    expect(buildStepViews([])).toEqual([])
  })
})

describe('buildListEntry', () => {
  const funnel: Funnel = {
    id: 'f1',
    site_id: 's1',
    name: 'Checkout',
    steps: [
      { step_index: 0, trigger_type: 'pageview', url_pattern: '/' },
      { step_index: 1, trigger_type: 'pageview', url_pattern: '/buy' },
    ],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
  }

  it('reports the step count', () => {
    expect(buildListEntry(funnel).stepCount).toBe(2)
  })

  it('leaves conversion null until a response is supplied', () => {
    expect(buildListEntry(funnel).overallConversionPct).toBeNull()
  })

  it('derives conversion when a response is supplied', () => {
    expect(buildListEntry(funnel, response).overallConversionPct).toBeCloseTo(12.3)
  })
})
