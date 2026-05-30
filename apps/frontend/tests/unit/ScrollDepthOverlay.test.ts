// =============================================================================
// ScrollDepthOverlay — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for ScrollDepthOverlay.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import ScrollDepthOverlay from '@/components/behaviour/ScrollDepthOverlay.vue'
import en from '@/i18n/locales/en.json'
import { numberFormatsEn } from '@/i18n/numberFormats'
import type { ScrollDepthBar } from '@/components/behaviour/heatmapModel'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: { en },
  numberFormats: { en: numberFormatsEn },
})

const bars: ScrollDepthBar[] = [
  { depthPct: 100, sessionsPct: 20 },
  { depthPct: 50, sessionsPct: 55 },
  { depthPct: 25, sessionsPct: 80 },
]

function renderOverlay(props: Partial<InstanceType<typeof ScrollDepthOverlay>['$props']> = {}) {
  return render(ScrollDepthOverlay, {
    props: { bars, foldDepthPct: 50, ...props },
    global: { plugins: [i18n] },
  })
}

describe('ScrollDepthOverlay', () => {
  it('paints a vertical gradient from the depth distribution', () => {
    const { container } = renderOverlay()
    const fill = container.querySelector('.scroll-overlay__fill') as HTMLElement
    expect(fill.style.background).toContain('linear-gradient')
  })

  it('positions the fold line at the median reach', () => {
    const { container } = renderOverlay({ foldDepthPct: 50 })
    const fold = container.querySelector('.scroll-overlay__fold') as HTMLElement
    expect(fold).not.toBeNull()
    expect(fold.style.top).toBe('50%')
  })

  it('omits the fold line when the fold is unknown', () => {
    const { container } = renderOverlay({ foldDepthPct: null })
    expect(container.querySelector('.scroll-overlay__fold')).toBeNull()
  })

  it('renders a transparent fill when there is no data', () => {
    const { container } = renderOverlay({ bars: [], foldDepthPct: null })
    const fill = container.querySelector('.scroll-overlay__fill') as HTMLElement
    expect(fill.style.background).toBe('transparent')
  })

  it('hides itself from assistive technology', () => {
    const { container } = renderOverlay()
    expect(container.querySelector('.scroll-overlay')?.getAttribute('aria-hidden')).toBe('true')
  })
})
