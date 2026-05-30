// =============================================================================
// Statflow Dashboard — Locale Store
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SupportedLocale } from '@/types'

const STORAGE_KEY = 'sf-locale'

export const useLocaleStore = defineStore('locale', () => {
  const { locale } = useI18n()
  const currentLocale = ref<SupportedLocale>(locale.value as SupportedLocale)

  function setLocale(next: SupportedLocale) {
    locale.value = next
    currentLocale.value = next
    localStorage.setItem(STORAGE_KEY, next)
    document.documentElement.lang = next
    document.documentElement.dir = 'ltr'
  }

  return { currentLocale, setLocale }
})
