// =============================================================================
// useCommandPalette — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import { render } from '@testing-library/vue'
import { useCommandPalette } from '@/composables/useCommandPalette'

function mountConsumer(): ReturnType<typeof useCommandPalette> {
  const out: { api?: ReturnType<typeof useCommandPalette> } = {}
  const Host = defineComponent({
    setup() {
      out.api = useCommandPalette()
      return () => null
    },
  })
  render(Host)
  return out.api as ReturnType<typeof useCommandPalette>
}

describe('useCommandPalette', () => {
  beforeEach(() => {
    const { close } = mountConsumer()
    close()
  })

  it('opens, closes, and toggles', () => {
    const { isOpen, open, close, toggle } = mountConsumer()
    expect(isOpen.value).toBe(false)
    open()
    expect(isOpen.value).toBe(true)
    close()
    expect(isOpen.value).toBe(false)
    toggle()
    expect(isOpen.value).toBe(true)
  })

  it('toggles open on the ⌘K / Ctrl+K shortcut', async () => {
    const { isOpen } = mountConsumer()
    expect(isOpen.value).toBe(false)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }))
    expect(isOpen.value).toBe(true)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
    expect(isOpen.value).toBe(false)
  })

  it('ignores plain "k" keypresses without a modifier', () => {
    const { isOpen } = mountConsumer()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k' }))
    expect(isOpen.value).toBe(false)
  })
})
