// =============================================================================
// PageWireframe — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: 100% lines + branches for PageWireframe.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render } from '@testing-library/vue'
import PageWireframe from '@/components/behaviour/PageWireframe.vue'

function renderWireframe(props: Partial<InstanceType<typeof PageWireframe>['$props']> = {}) {
  return render(PageWireframe, { props })
}

describe('PageWireframe', () => {
  it('defaults to a 16/10 desktop aspect ratio', () => {
    const { container } = renderWireframe()
    const root = container.querySelector('.wireframe') as HTMLElement
    expect(root.style.aspectRatio).toBe('16 / 10')
  })

  it('uses a portrait ratio for mobile', () => {
    const { container } = renderWireframe({ device: 'mobile' })
    const root = container.querySelector('.wireframe') as HTMLElement
    expect(root.style.aspectRatio).toBe('9 / 16')
  })

  it('uses a 3/4 ratio for tablet', () => {
    const { container } = renderWireframe({ device: 'tablet' })
    const root = container.querySelector('.wireframe') as HTMLElement
    expect(root.style.aspectRatio).toBe('3 / 4')
  })

  it('is decorative and hidden from assistive technology', () => {
    const { container } = renderWireframe()
    expect(container.querySelector('.wireframe')?.getAttribute('aria-hidden')).toBe('true')
  })
})
