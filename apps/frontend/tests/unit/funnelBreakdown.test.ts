// =============================================================================
// funnelBreakdown / funnelGoalFilter — adapter tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  FUNNEL_BREAKDOWN_DIMENSIONS,
  buildFunnelBreakdown,
} from '@/components/funnels/funnelBreakdown'
import { goalFilterForFunnel } from '@/components/funnels/funnelGoalFilter'
import type { BreakdownResponse, Funnel } from '@/api/types'

const breakdown: BreakdownResponse = {
  property: 'device_type',
  period: { from: '2026-01-01', to: '2026-01-31' },
  rows: [
    { value: 'desktop', metrics: { conversion_rate: 18.2, visitors: 500 } },
    { value: 'mobile', metrics: { conversion_rate: 9.1, visitors: 800 } },
    { value: 'tablet', metrics: { visitors: 50 } },
  ],
  total_rows: 3,
}

describe('buildFunnelBreakdown', () => {
  it('maps rows to categories and conversion values by default', () => {
    const data = buildFunnelBreakdown(breakdown)
    expect(data.categories).toEqual(['desktop', 'mobile', 'tablet'])
    expect(data.values).toEqual([18.2, 9.1, 0])
  })

  it('defaults a missing metric to zero', () => {
    expect(buildFunnelBreakdown(breakdown).values[2]).toBe(0)
  })

  it('honours an explicit metric', () => {
    const data = buildFunnelBreakdown(breakdown, { metric: 'visitors' })
    expect(data.values).toEqual([500, 800, 50])
  })

  it('applies a custom label mapper', () => {
    const data = buildFunnelBreakdown(breakdown, { labelFor: (v) => v.toUpperCase() })
    expect(data.categories).toEqual(['DESKTOP', 'MOBILE', 'TABLET'])
  })

  it('limits the number of bars', () => {
    const data = buildFunnelBreakdown(breakdown, { limit: 2 })
    expect(data.categories).toHaveLength(2)
  })

  it('is safe for a nullish response', () => {
    expect(buildFunnelBreakdown(null)).toEqual({ categories: [], values: [] })
  })

  it('exposes the three spec dimensions', () => {
    expect(FUNNEL_BREAKDOWN_DIMENSIONS).toEqual(['device_type', 'country', 'referrer_domain'])
  })
})

describe('goalFilterForFunnel', () => {
  const base: Funnel = {
    id: 'f1',
    site_id: 's1',
    name: 'F',
    steps: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
  }

  it('returns a pathname filter for a pageview final step', () => {
    const funnel: Funnel = {
      ...base,
      steps: [
        { step_index: 0, trigger_type: 'pageview', url_pattern: '/' },
        { step_index: 1, trigger_type: 'pageview', url_pattern: '/checkout/success' },
      ],
    }
    expect(goalFilterForFunnel(funnel)).toEqual({
      property: 'pathname',
      operator: 'eq',
      value: '/checkout/success',
    })
  })

  it('returns an event filter for an event final step', () => {
    const funnel: Funnel = {
      ...base,
      steps: [
        { step_index: 0, trigger_type: 'pageview', url_pattern: '/' },
        { step_index: 1, trigger_type: 'event', event_name: 'purchase' },
      ],
    }
    expect(goalFilterForFunnel(funnel)).toEqual({
      property: 'event_name',
      operator: 'eq',
      value: 'purchase',
    })
  })

  it('uses the highest step_index regardless of array order', () => {
    const funnel: Funnel = {
      ...base,
      steps: [
        { step_index: 1, trigger_type: 'event', event_name: 'purchase' },
        { step_index: 0, trigger_type: 'pageview', url_pattern: '/' },
      ],
    }
    expect(goalFilterForFunnel(funnel)?.value).toBe('purchase')
  })

  it('returns null when the final step has no matcher', () => {
    const funnel: Funnel = {
      ...base,
      steps: [{ step_index: 0, trigger_type: 'pageview' }],
    }
    expect(goalFilterForFunnel(funnel)).toBeNull()
  })

  it('returns null for an empty or nullish funnel', () => {
    expect(goalFilterForFunnel(base)).toBeNull()
    expect(goalFilterForFunnel(null)).toBeNull()
  })
})
