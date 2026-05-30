// =============================================================================
// Statflow Dashboard — useTheme Composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { useThemeStore } from '@/stores/theme'
import { storeToRefs } from 'pinia'

/**
 * Composable providing reactive access to the active theme and the toggle action.
 * The theme is persisted to localStorage and reflected on document.documentElement
 * via the store's watcher.
 */
export function useTheme() {
  const store = useThemeStore()
  const { theme, isDark } = storeToRefs(store)
  return { theme, isDark, setTheme: store.setTheme, toggleTheme: store.toggleTheme }
}
