// =============================================================================
// detectLocale — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { detectLocale } from '@/i18n/detectLocale'

describe('detectLocale', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('returns stored locale when valid "en" is in localStorage', () => {
    localStorage.setItem('sf-locale', 'en')
    expect(detectLocale()).toBe('en')
  })

  it('returns stored locale when valid "fr" is in localStorage', () => {
    localStorage.setItem('sf-locale', 'fr')
    expect(detectLocale()).toBe('fr')
  })

  it('ignores invalid stored locale and falls back to browser language', () => {
    localStorage.setItem('sf-locale', 'de')
    Object.defineProperty(navigator, 'language', {
      get: () => 'fr-FR',
      configurable: true,
    })
    expect(detectLocale()).toBe('fr')
  })

  it('returns "fr" when navigator.language is "fr-FR"', () => {
    Object.defineProperty(navigator, 'language', {
      get: () => 'fr-FR',
      configurable: true,
    })
    expect(detectLocale()).toBe('fr')
  })

  it('returns "en" as fallback when browser language is unsupported', () => {
    localStorage.removeItem('sf-locale')
    Object.defineProperty(navigator, 'language', {
      get: () => 'de-DE',
      configurable: true,
    })
    expect(detectLocale()).toBe('en')
  })

  it('returns "en" as fallback when navigator.language is empty', () => {
    localStorage.removeItem('sf-locale')
    Object.defineProperty(navigator, 'language', {
      get: () => '',
      configurable: true,
    })
    expect(detectLocale()).toBe('en')
  })
})
