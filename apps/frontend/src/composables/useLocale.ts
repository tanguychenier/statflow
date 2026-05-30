// =============================================================================
// Statflow Dashboard — useLocale composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { useI18n } from 'vue-i18n'
import type { SupportedLocale } from '@/types'

const STORAGE_KEY = 'sf-locale'

/**
 * Reactive locale access with instant switching (no reload). Persists the
 * choice and keeps the document `lang`/`dir` attributes in sync — both EN and
 * FR are LTR, but the attribute is set explicitly for assistive technology.
 */
export function useLocale() {
  const { locale } = useI18n()

  function setLocale(next: SupportedLocale) {
    locale.value = next
    localStorage.setItem(STORAGE_KEY, next)
    document.documentElement.lang = next
    document.documentElement.dir = 'ltr'
  }

  return { locale, setLocale }
}
