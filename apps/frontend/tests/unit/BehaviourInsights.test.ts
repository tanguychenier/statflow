// =============================================================================
// BehaviourInsights — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for BehaviourInsights.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import BehaviourInsights from '@/components/behaviour/BehaviourInsights.vue'
import en from '@/i18n/locales/en.json'
import { numberFormatsEn } from '@/i18n/numberFormats'
import type { BehaviourInsight } from '@/components/behaviour/heatmapModel'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: { en },
  numberFormats: { en: numberFormatsEn },
})

const rage: BehaviourInsight[] = [{ kind: 'rage', selector: '.submit', count: 42, sharePct: 70 }]
const dead: BehaviourInsight[] = [{ kind: 'dead', selector: '.banner', count: 13, sharePct: 30 }]

function renderInsights(props: Partial<InstanceType<typeof BehaviourInsights>['$props']> = {}) {
  return render(BehaviourInsights, {
    props: { rage, dead, ...props },
    global: { plugins: [i18n] },
  })
}

describe('BehaviourInsights — populated', () => {
  it('renders both section titles', () => {
    renderInsights()
    expect(screen.getByText('Rage clicks')).toBeInTheDocument()
    expect(screen.getByText('Dead clicks')).toBeInTheDocument()
  })

  it('lists the offending selectors', () => {
    renderInsights()
    expect(screen.getByText('.submit')).toBeInTheDocument()
    expect(screen.getByText('.banner')).toBeInTheDocument()
  })
})

describe('BehaviourInsights — empty', () => {
  it('shows the none copy for both groups', () => {
    renderInsights({ rage: [], dead: [] })
    expect(screen.getAllByText('No frustration signals on this page.')).toHaveLength(2)
  })
})

describe('BehaviourInsights — loading', () => {
  it('renders busy lists and no selectors while loading', () => {
    const { container } = renderInsights({ loading: true })
    expect(container.querySelectorAll('[aria-busy="true"]')).toHaveLength(2)
    expect(screen.queryByText('.submit')).not.toBeInTheDocument()
  })
})
