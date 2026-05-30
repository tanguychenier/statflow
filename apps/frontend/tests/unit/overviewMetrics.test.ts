// =============================================================================
// overviewMetrics — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for overviewMetrics.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  buildOverviewMetrics,
  OVERVIEW_METRICS,
  metricTitleKey,
  type MetricFormatters,
} from '@/components/overview/overviewMetrics'
import en from '@/i18n/locales/en.json'
import type { AggregateMetricsResponse, MetricName, TimeSeriesResponse } from '@/api/types'

const fmt: MetricFormatters = {
  count: (n) => n.toLocaleString('en-US'),
  percent: (n) => `${n.toFixed(1)}%`,
  duration: (n) => `${Math.floor(n / 60)}m ${String(n % 60).padStart(2, '0')}s`,
}

function aggregate(overrides: Partial<AggregateMetricsResponse> = {}): AggregateMetricsResponse {
  return {
    period: { from: '2024-12-01', to: '2024-12-31' },
    metrics: {
      visitors: 61412,
      pageviews: 214880,
      sessions: 84203,
      bounce_rate: 42.1,
      avg_duration: 124,
    },
    ...overrides,
  }
}

describe('buildOverviewMetrics — composition', () => {
  it('returns one card per overview metric in display order', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.map((c) => c.id)).toEqual([...OVERVIEW_METRICS])
  })

  it('maps each card to its i18n title key', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    const byId = Object.fromEntries(cards.map((c) => [c.id, c.titleKey]))
    expect(byId.visitors).toBe('overview.metrics.users')
    expect(byId.bounce_rate).toBe('overview.metrics.bounceRate')
    expect(byId.avg_duration).toBe('overview.metrics.avgDuration')
  })
})

describe('metricTitleKey — label mapping', () => {
  const ALL_METRICS: MetricName[] = [
    'visitors',
    'pageviews',
    'sessions',
    'bounce_rate',
    'avg_duration',
    'events',
    'conversion_rate',
  ]

  function leaf(path: string): unknown {
    return path.split('.').reduce<unknown>(
      (node, segment) =>
        node && typeof node === 'object' ? (node as Record<string, unknown>)[segment] : undefined,
      en,
    )
  }

  it('maps events and conversion rate to their own labels, not unrelated metrics', () => {
    expect(metricTitleKey('events')).toBe('overview.metrics.events')
    expect(metricTitleKey('conversion_rate')).toBe('overview.metrics.conversionRate')
  })

  it('resolves every metric title key to an existing i18n string', () => {
    for (const metric of ALL_METRICS) {
      const value = leaf(metricTitleKey(metric))
      expect(typeof value, `${metric} → ${metricTitleKey(metric)}`).toBe('string')
    }
  })
})

describe('buildOverviewMetrics — defaults', () => {
  it('defaults missing metrics to a zero-formatted value', () => {
    const cards = buildOverviewMetrics(aggregate({ metrics: {} }), fmt)
    expect(cards.find((c) => c.id === 'visitors')?.value).toBe('0')
  })

  it('renders five well-formed cards even with a null aggregate', () => {
    const cards = buildOverviewMetrics(null, fmt)
    expect(cards).toHaveLength(5)
    expect(cards.every((c) => c.value === '0' || c.value.includes('m '))).toBe(true)
  })
})

describe('buildOverviewMetrics — value formatting', () => {
  it('formats counts with the count formatter', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'visitors')?.value).toBe('61,412')
  })

  it('formats bounce rate as a percentage', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'bounce_rate')?.value).toBe('42.1%')
  })

  it('formats avg duration as a clock string', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'avg_duration')?.value).toBe('2m 04s')
  })
})

describe('buildOverviewMetrics — trend semantics', () => {
  it('omits the trend when no comparison block is present', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.every((c) => c.trend === undefined)).toBe(true)
  })

  it('builds an upward positive trend for a rising count metric', () => {
    const cards = buildOverviewMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { visitors: 8.1 },
        },
      }),
      fmt,
    )
    const visitors = cards.find((c) => c.id === 'visitors')
    expect(visitors?.trend).toEqual({ value: 8.1, direction: 'up', positiveIsUp: true })
  })

  it('marks bounce rate as positiveIsUp=false (inverted metric)', () => {
    const cards = buildOverviewMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { bounce_rate: -3.2 },
        },
      }),
      fmt,
    )
    const bounce = cards.find((c) => c.id === 'bounce_rate')
    expect(bounce?.trend).toEqual({ value: -3.2, direction: 'down', positiveIsUp: false })
  })

  it('produces a neutral direction for a zero change', () => {
    const cards = buildOverviewMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { visitors: 0 },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'visitors')?.trend?.direction).toBe('neutral')
  })

  it('drops the trend when change_pct is null', () => {
    const cards = buildOverviewMetrics(
      aggregate({
        comparison: {
          period: { from: '2024-11-01', to: '2024-11-30' },
          metrics: {},
          change_pct: { visitors: null },
        },
      }),
      fmt,
    )
    expect(cards.find((c) => c.id === 'visitors')?.trend).toBeUndefined()
  })
})

describe('buildOverviewMetrics — sparklines', () => {
  const series: TimeSeriesResponse = {
    interval: 'day',
    period: { from: '2024-12-01', to: '2024-12-03' },
    series: [
      { bucket: '2024-12-01', metrics: { visitors: 100, bounce_rate: 40 } },
      { bucket: '2024-12-02', metrics: { visitors: 120 } },
      { bucket: '2024-12-03', metrics: { visitors: 90, bounce_rate: 45 } },
    ],
  }

  it('extracts the per-metric series for the sparkline', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt, series)
    expect(cards.find((c) => c.id === 'visitors')?.sparkData).toEqual([100, 120, 90])
  })

  it('fills absent bucket metrics with zero', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt, series)
    expect(cards.find((c) => c.id === 'bounce_rate')?.sparkData).toEqual([40, 0, 45])
  })

  it('returns empty sparkData when no series supplied', () => {
    const cards = buildOverviewMetrics(aggregate(), fmt)
    expect(cards.find((c) => c.id === 'visitors')?.sparkData).toEqual([])
  })
})
