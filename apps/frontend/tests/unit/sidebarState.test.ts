// =============================================================================
// useSidebarState — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { nextTick } from 'vue'
import { useSidebarState } from '@/composables/useSidebarState'

describe('useSidebarState', () => {
  it('toggles the collapsed state', () => {
    const { collapsed, toggle } = useSidebarState()
    const initial = collapsed.value
    toggle()
    expect(collapsed.value).toBe(!initial)
    toggle()
    expect(collapsed.value).toBe(initial)
  })

  it('sets the collapsed state explicitly and persists it', async () => {
    const { collapsed, setCollapsed } = useSidebarState()
    setCollapsed(true)
    expect(collapsed.value).toBe(true)
    await nextTick()
    expect(localStorage.getItem('sf-sidebar-collapsed')).toBe('true')
  })

  it('shares one state across consumers (singleton)', () => {
    const a = useSidebarState()
    const b = useSidebarState()
    a.setCollapsed(false)
    expect(b.collapsed.value).toBe(false)
    a.setCollapsed(true)
    expect(b.collapsed.value).toBe(true)
  })
})
