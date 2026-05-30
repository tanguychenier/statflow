// =============================================================================
// Theme Store — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { nextTick } from 'vue'
import { useThemeStore } from '@/stores/theme'

describe('useThemeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    // Reset html attribute
    document.documentElement.removeAttribute('data-theme')
  })

  it('defaults to dark theme when nothing is stored', () => {
    const store = useThemeStore()
    expect(store.theme).toBe('dark')
  })

  it('restores theme from localStorage', () => {
    localStorage.setItem('sf-theme', 'light')
    setActivePinia(createPinia())
    const store = useThemeStore()
    expect(store.theme).toBe('light')
  })

  it('isDark is true when theme is dark', () => {
    const store = useThemeStore()
    store.setTheme('dark')
    expect(store.isDark).toBe(true)
  })

  it('isDark is false when theme is light', () => {
    const store = useThemeStore()
    store.setTheme('light')
    expect(store.isDark).toBe(false)
  })

  it('toggleTheme switches dark to light', () => {
    const store = useThemeStore()
    store.setTheme('dark')
    store.toggleTheme()
    expect(store.theme).toBe('light')
  })

  it('toggleTheme switches light to dark', () => {
    const store = useThemeStore()
    store.setTheme('light')
    store.toggleTheme()
    expect(store.theme).toBe('dark')
  })

  it('persists theme change to localStorage', async () => {
    const store = useThemeStore()
    store.setTheme('light')
    await nextTick()
    expect(localStorage.getItem('sf-theme')).toBe('light')
  })

  it('sets data-theme attribute on html element', async () => {
    const store = useThemeStore()
    store.setTheme('light')
    await nextTick()
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
  })

  it('sets data-theme to dark when switching back', async () => {
    const store = useThemeStore()
    store.setTheme('light')
    await nextTick()
    store.setTheme('dark')
    await nextTick()
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })
})
