// =============================================================================
// Pages & Sources — tab configuration tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for tabs.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  TABS,
  UTM_SUBTABS,
  METRIC_COLUMNS,
  REQUESTED_METRICS,
  resolveTab,
  resolveUtmSubTab,
  tabConfig,
  utmProperty,
  utmDimensionKey,
} from '@/components/pagessources/tabs'

describe('tab configuration', () => {
  it('declares the six screen tabs in order', () => {
    expect(TABS.map((tab) => tab.id)).toEqual([
      'pages',
      'entry',
      'exit',
      'sources',
      'referrers',
      'utm',
    ])
  })

  it('maps each tab to a breakdown property', () => {
    expect(tabConfig('pages').property).toBe('pathname')
    expect(tabConfig('entry').property).toBe('entry_page')
    expect(tabConfig('exit').property).toBe('exit_page')
    expect(tabConfig('referrers').property).toBe('referrer_domain')
  })

  it('exposes the metric column set with visitors as the default leader', () => {
    expect(METRIC_COLUMNS[0].metric).toBe('visitors')
    expect(REQUESTED_METRICS).toEqual(METRIC_COLUMNS.map((c) => c.metric))
  })
})

describe('resolveTab', () => {
  it('passes through a known tab id', () => {
    expect(resolveTab('sources')).toBe('sources')
  })

  it('defaults unknown / non-string values to "pages"', () => {
    expect(resolveTab('bogus')).toBe('pages')
    expect(resolveTab(undefined)).toBe('pages')
    expect(resolveTab(42)).toBe('pages')
    expect(resolveTab(['sources'])).toBe('pages')
  })
})

describe('resolveUtmSubTab', () => {
  it('passes through a known sub-tab', () => {
    expect(resolveUtmSubTab('utm_medium')).toBe('utm_medium')
    expect(resolveUtmSubTab('utm_source')).toBe('utm_source')
  })

  it('defaults unknown values to utm_campaign', () => {
    expect(resolveUtmSubTab('nope')).toBe('utm_campaign')
    expect(resolveUtmSubTab(undefined)).toBe('utm_campaign')
  })
})

describe('tabConfig fallback', () => {
  it('falls back to the first tab for an unknown id', () => {
    // @ts-expect-error exercising the runtime guard with a bad id
    expect(tabConfig('does-not-exist')).toBe(TABS[0])
  })
})

describe('utm helpers', () => {
  it('maps a sub-tab to its breakdown property and dimension key', () => {
    expect(UTM_SUBTABS).toEqual(['utm_campaign', 'utm_medium', 'utm_source'])
    expect(utmProperty('utm_medium')).toBe('utm_medium')
    expect(utmDimensionKey('utm_source')).toBe('dimensions.utm_source')
  })
})
