// =============================================================================
// Statflow Dashboard — Theme Store
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { Theme } from '@/types'

const STORAGE_KEY = 'sf-theme'

function resolveInitialTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'light' || stored === 'dark') return stored
  // Default to dark theme as per design system specification
  return 'dark'
}

export const useThemeStore = defineStore('theme', () => {
  const theme = ref<Theme>(resolveInitialTheme())

  const isDark = computed(() => theme.value === 'dark')

  function setTheme(next: Theme) {
    theme.value = next
  }

  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  // Sync the data-theme attribute on <html> and persist to localStorage
  watch(
    theme,
    (value) => {
      document.documentElement.setAttribute('data-theme', value)
      localStorage.setItem(STORAGE_KEY, value)
    },
    { immediate: true },
  )

  return { theme, isDark, setTheme, toggleTheme }
})
