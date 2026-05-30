// =============================================================================
// publicPeriod — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for publicPeriod.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import { parseIsoDate, parsePublicPeriod } from '@/components/publicshared/publicPeriod'

describe('parseIsoDate', () => {
  it('parses a valid YYYY-MM-DD at local noon', () => {
    const date = parseIsoDate('2024-12-25')
    expect(date).not.toBeNull()
    expect(date?.getFullYear()).toBe(2024)
    expect(date?.getMonth()).toBe(11)
    expect(date?.getDate()).toBe(25)
    expect(date?.getHours()).toBe(12)
  })

  it('returns null for null, undefined or empty input', () => {
    expect(parseIsoDate(null)).toBeNull()
    expect(parseIsoDate(undefined)).toBeNull()
    expect(parseIsoDate('')).toBeNull()
  })

  it('returns null for malformed strings', () => {
    expect(parseIsoDate('2024/12/25')).toBeNull()
    expect(parseIsoDate('25-12-2024')).toBeNull()
    expect(parseIsoDate('2024-12')).toBeNull()
    expect(parseIsoDate('not-a-date')).toBeNull()
    expect(parseIsoDate('2024-12-25T10:00:00Z')).toBeNull()
  })

  it('rejects calendar overflow dates', () => {
    expect(parseIsoDate('2024-02-31')).toBeNull()
    expect(parseIsoDate('2023-02-29')).toBeNull()
    expect(parseIsoDate('2024-13-01')).toBeNull()
  })

  it('accepts a valid leap day', () => {
    expect(parseIsoDate('2024-02-29')).not.toBeNull()
  })
})

describe('parsePublicPeriod', () => {
  it('parses a valid period DTO into a Date pair', () => {
    const period = parsePublicPeriod({ from: '2024-12-01', to: '2024-12-31' })
    expect(period).not.toBeNull()
    expect(period?.from.getDate()).toBe(1)
    expect(period?.to.getDate()).toBe(31)
  })

  it('returns null when the DTO is null or undefined', () => {
    expect(parsePublicPeriod(null)).toBeNull()
    expect(parsePublicPeriod(undefined)).toBeNull()
  })

  it('returns null when either bound is malformed', () => {
    expect(parsePublicPeriod({ from: 'bad', to: '2024-12-31' })).toBeNull()
    expect(parsePublicPeriod({ from: '2024-12-01', to: 'bad' })).toBeNull()
  })
})
