// =============================================================================
// countries — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for countries.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import { countryName, countryFlag } from '@/components/overview/countries'

describe('countryName', () => {
  it('maps a known alpha-2 code to its English name', () => {
    expect(countryName('FR')).toBe('France')
    expect(countryName('US')).toBe('United States of America')
  })

  it('is case-insensitive on the input code', () => {
    expect(countryName('de')).toBe('Germany')
  })

  it('falls back to the upper-cased code for an unknown country', () => {
    expect(countryName('zz')).toBe('ZZ')
  })
})

describe('countryFlag', () => {
  it('produces the regional-indicator flag for a valid code', () => {
    // 🇫🇷 = U+1F1EB U+1F1F7
    expect(countryFlag('FR')).toBe('\u{1F1EB}\u{1F1F7}')
  })

  it('upper-cases lowercase input before composing', () => {
    expect(countryFlag('us')).toBe('\u{1F1FA}\u{1F1F8}')
  })

  it('returns an empty string for a non-two-letter code', () => {
    expect(countryFlag('USA')).toBe('')
    expect(countryFlag('1')).toBe('')
    expect(countryFlag('')).toBe('')
  })
})
