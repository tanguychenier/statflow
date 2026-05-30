// =============================================================================
// useTheme Composable — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { nextTick } from 'vue'
import { useTheme } from '@/composables/useTheme'

describe('useTheme', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-theme')
  })

  it('returns isDark as true by default', () => {
    const { isDark } = useTheme()
    expect(isDark.value).toBe(true)
  })

  it('returns theme as "dark" by default', () => {
    const { theme } = useTheme()
    expect(theme.value).toBe('dark')
  })

  it('setTheme changes the theme to light', async () => {
    const { theme, setTheme } = useTheme()
    setTheme('light')
    await nextTick()
    expect(theme.value).toBe('light')
  })

  it('isDark becomes false after setTheme("light")', async () => {
    const { isDark, setTheme } = useTheme()
    setTheme('light')
    await nextTick()
    expect(isDark.value).toBe(false)
  })

  it('toggleTheme switches theme', async () => {
    const { theme, toggleTheme } = useTheme()
    expect(theme.value).toBe('dark')
    toggleTheme()
    await nextTick()
    expect(theme.value).toBe('light')
  })
})
