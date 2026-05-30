// =============================================================================
// BehaviourStatsPanel — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for BehaviourStatsPanel.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import BehaviourStatsPanel from '@/components/behaviour/BehaviourStatsPanel.vue'
import en from '@/i18n/locales/en.json'
import { numberFormatsEn } from '@/i18n/numberFormats'
import type { ScrollDepthBar, TopElement } from '@/components/behaviour/heatmapModel'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: { en },
  numberFormats: { en: numberFormatsEn },
})

const topElements: TopElement[] = [
  { selector: '.cta', count: 38, sharePct: 38 },
  { selector: '.pricing', count: 21, sharePct: 21 },
]

const scrollDepth: ScrollDepthBar[] = [
  { depthPct: 100, sessionsPct: 92 },
  { depthPct: 75, sessionsPct: 71 },
]

function renderPanel(props: Partial<InstanceType<typeof BehaviourStatsPanel>['$props']> = {}) {
  return render(BehaviourStatsPanel, {
    props: {
      totalEvents: 8203,
      uniqueUsers: 4012,
      sampleSize: 6841,
      topElements,
      scrollDepth,
      ...props,
    },
    global: { plugins: [i18n] },
  })
}

describe('BehaviourStatsPanel — loaded state', () => {
  it('renders the three headline metrics', () => {
    renderPanel()
    expect(screen.getByText('Total clicks')).toBeInTheDocument()
    expect(screen.getByText('Unique users')).toBeInTheDocument()
    expect(screen.getByText('Sample size')).toBeInTheDocument()
  })

  it('renders the top elements list', () => {
    renderPanel()
    expect(screen.getByText('.cta')).toBeInTheDocument()
    expect(screen.getByText('.pricing')).toBeInTheDocument()
  })

  it('renders the scroll-depth buckets', () => {
    renderPanel()
    expect(screen.getByText('100%')).toBeInTheDocument()
    expect(screen.getByText('75%')).toBeInTheDocument()
  })

  it('shows an em dash when unique users is null', () => {
    renderPanel({ uniqueUsers: null })
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('exposes a polite live region for in-place updates', () => {
    const { container } = renderPanel()
    expect(container.querySelector('[aria-live="polite"]')).toBeInTheDocument()
  })
})

describe('BehaviourStatsPanel — empty data', () => {
  it('falls back to the no-data copy for both lists', () => {
    renderPanel({ topElements: [], scrollDepth: [] })
    expect(screen.getAllByText('No data available')).toHaveLength(2)
  })
})

describe('BehaviourStatsPanel — loading state', () => {
  it('hides metric values and shows skeletons while loading', () => {
    const { container } = renderPanel({ loading: true })
    expect(screen.queryByText('6,841')).not.toBeInTheDocument()
    expect(container.querySelectorAll('.sf-skeleton, [class*="skeleton"]').length).toBeGreaterThan(0)
  })
})
