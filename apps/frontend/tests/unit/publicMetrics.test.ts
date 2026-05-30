// =============================================================================
// publicMetrics — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for publicMetrics.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  buildPublicMetrics,
  PUBLIC_METRICS,
  type PublicMetricFormatters,
} from '@/components/publicshared/publicMetrics'
import type { AggregateMetricsResponse } from '@/api/types'

const fmt: PublicMetricFormatters = {
  count: (n) => n.toLocaleString('en-US'),
  percent: (n) => `${n.toFixed(1)}%`,
  duration: (n) => `${Math.floor(n / 60)}m ${String(n % 60).padStart(2, '0')}s`,
}

function aggregate(overrides: Partial<AggregateMetricsResponse> = {}): AggregateMetricsResponse {
  return {
    period: { from: '2024-12-01', to: '2024-12-31' },
    metrics: {
      sessions: 84203,
      visitors: 61412,
      pageviews: 214880,
      bounce_rate: 42.1,
      avg_duration: 124,
    },
    ...overrides,
  }
}

describe('buildPublicMetrics — composition', () => {
  it('returns one card per public metric in display order', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards.map((c) => c.id)).toEqual([...PUBLIC_METRICS])
  })

  it('leads with sessions per the wireframe order', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards[0].id).toBe('sessions')
  })

  it('maps each card to its publicDashboard i18n title key', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    const byId = Object.fromEntries(cards.map((c) => [c.id, c.titleKey]))
    expect(byId.sessions).toBe('publicDashboard.metrics.sessions')
    expect(byId.visitors).toBe('publicDashboard.metrics.users')
    expect(byId.bounce_rate).toBe('publicDashboard.metrics.bounceRate')
    expect(byId.avg_duration).toBe('publicDashboard.metrics.avgDuration')
  })

  it('defaults missing metrics to a zero-formatted value', () => {
    const cards = buildPublicMetrics(aggregate({ metrics: {} }), fmt)
    expect(cards.find((c) => c.id === 'sessions')?.value).toBe('0')
  })

  it('renders five well-formed cards with a null aggregate', () => {
    const cards = buildPublicMetrics(null, fmt)
    expect(cards).toHaveLength(5)
    expect(cards.every((c) => c.value === '0' || c.value.includes('m '))).toBe(true)
  })

  it('renders five well-formed cards with an undefined aggregate', () => {
    const cards = buildPublicMetrics(undefined, fmt)
    expect(cards).toHaveLength(5)
  })
})

describe('buildPublicMetrics — value formatting', () => {
  it('formats counts with the count formatter', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'visitors')?.value).toBe('61,412')
  })

  it('formats bounce rate as a percentage', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'bounce_rate')?.value).toBe('42.1%')
  })

  it('formats avg duration as a clock string', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'avg_duration')?.value).toBe('2m 04s')
  })
})

describe('buildPublicMetrics — trend semantics', () => {
  it('omits the trend when no comparison block is present', () => {
    const cards = buildPublicMetrics(aggregate(), fmt)
    expect(cards.every((c) => c.trend === undefined)).toBe(true)
  })

  it('builds an upward positive trend for a rising count metric', () => {
    const cards = buildPublicMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { sessions: 8.1 },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'sessions')?.trend).toEqual({
      value: 8.1,
      direction: 'up',
      positiveIsUp: true,
    })
  })

  it('marks bounce rate as positiveIsUp=false (inverted metric)', () => {
    const cards = buildPublicMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { bounce_rate: -3.2 },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'bounce_rate')?.trend).toEqual({
      value: -3.2,
      direction: 'down',
      positiveIsUp: false,
    })
  })

  it('produces a neutral direction for a zero change', () => {
    const cards = buildPublicMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { sessions: 0 },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'sessions')?.trend?.direction).toBe('neutral')
  })

  it('drops the trend when change_pct is null', () => {
    const cards = buildPublicMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { sessions: null },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'sessions')?.trend).toBeUndefined()
  })

  it('drops the trend when change_pct is NaN', () => {
    const cards = buildPublicMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { sessions: Number.NaN },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'sessions')?.trend).toBeUndefined()
  })
})
