// =============================================================================
// Statflow Dashboard — Locale Detection
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { SupportedLocale } from '@/types'

const SUPPORTED: SupportedLocale[] = ['en', 'fr']
const STORAGE_KEY = 'sf-locale'

/**
 * Detects the active locale using the following priority:
 * 1. User's explicit preference persisted in localStorage
 * 2. Browser navigator.language
 * 3. Default fallback ('en')
 */
export function detectLocale(): SupportedLocale {
  // 1. Stored preference
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored && (SUPPORTED as string[]).includes(stored)) {
    return stored as SupportedLocale
  }

  // 2. Browser language
  const browserLang = navigator.language.split('-')[0]
  if ((SUPPORTED as string[]).includes(browserLang)) {
    return browserLang as SupportedLocale
  }

  // 3. Fallback
  return 'en'
}
