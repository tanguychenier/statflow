// =============================================================================
// useLocale — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import { render } from '@testing-library/vue'
import { useLocale } from '@/composables/useLocale'
import { testI18n } from '../setup'

function capture(): ReturnType<typeof useLocale> {
  const out: { api?: ReturnType<typeof useLocale> } = {}
  const Host = defineComponent({
    setup() {
      out.api = useLocale()
      return () => null
    },
  })
  render(Host, { global: { plugins: [testI18n] } })
  return out.api as ReturnType<typeof useLocale>
}

describe('useLocale', () => {
  beforeEach(() => {
    localStorage.clear()
    testI18n.global.locale.value = 'en'
  })

  it('switches locale, persists it, and sets document attributes', () => {
    const { locale, setLocale } = capture()
    setLocale('fr')
    expect(locale.value).toBe('fr')
    expect(localStorage.getItem('sf-locale')).toBe('fr')
    expect(document.documentElement.lang).toBe('fr')
    expect(document.documentElement.dir).toBe('ltr')
  })

  it('switches back to en', () => {
    const { locale, setLocale } = capture()
    setLocale('fr')
    setLocale('en')
    expect(locale.value).toBe('en')
    expect(localStorage.getItem('sf-locale')).toBe('en')
  })
})
