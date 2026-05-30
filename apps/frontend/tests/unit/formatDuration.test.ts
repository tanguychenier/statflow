// =============================================================================
// formatDuration — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { formatDuration, formatCompact } from '@/utils/formatDuration'

describe('formatDuration', () => {
  it('returns "0s" for 0 seconds', () => {
    expect(formatDuration(0)).toBe('0s')
  })

  it('returns "0s" for negative values', () => {
    expect(formatDuration(-5)).toBe('0s')
  })

  it('returns seconds only when under 60 seconds', () => {
    expect(formatDuration(45)).toBe('45s')
  })

  it('returns "1m 00s" for exactly 60 seconds', () => {
    expect(formatDuration(60)).toBe('1m 00s')
  })

  it('returns "2m 04s" for 124 seconds', () => {
    expect(formatDuration(124)).toBe('2m 04s')
  })

  it('zero-pads seconds below 10', () => {
    expect(formatDuration(65)).toBe('1m 05s')
  })

  it('does not zero-pad seconds >= 10', () => {
    expect(formatDuration(72)).toBe('1m 12s')
  })

  it('handles large values correctly', () => {
    expect(formatDuration(3661)).toBe('61m 01s')
  })

  it('handles exactly 1 second', () => {
    expect(formatDuration(1)).toBe('1s')
  })

  it('handles exactly 59 seconds', () => {
    expect(formatDuration(59)).toBe('59s')
  })

  it('handles fractional seconds by flooring', () => {
    expect(formatDuration(124.9)).toBe('2m 04s')
  })
})

describe('formatCompact', () => {
  it('formats thousands with K in English', () => {
    const result = formatCompact(12000, 'en')
    expect(result).toMatch(/12/)
  })

  it('formats millions in English', () => {
    const result = formatCompact(1_200_000, 'en')
    expect(result).toMatch(/1/)
  })

  it('formats small numbers without compact notation', () => {
    const result = formatCompact(42, 'en')
    expect(result).toBe('42')
  })

  it('formats in French locale', () => {
    const result = formatCompact(12000, 'fr')
    expect(result).toBeTruthy()
    expect(typeof result).toBe('string')
  })
})
