// =============================================================================
// Statflow Dashboard — date-range helper + store extension tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import {
  toIsoDate,
  intervalForRange,
  useDateRangeStore,
} from '@/stores/dateRange'

describe('toIsoDate', () => {
  it('formats a date as YYYY-MM-DD in local time', () => {
    expect(toIsoDate(new Date(2025, 0, 5))).toBe('2025-01-05')
    expect(toIsoDate(new Date(2025, 11, 31))).toBe('2025-12-31')
  })
})

describe('intervalForRange', () => {
  it('selects hourly for <= 2 days', () => {
    const from = new Date(2025, 5, 1)
    expect(intervalForRange(from, new Date(2025, 5, 2))).toBe('hour')
  })

  it('selects daily for <= 90 days', () => {
    const from = new Date(2025, 5, 1)
    expect(intervalForRange(from, new Date(2025, 5, 30))).toBe('day')
  })

  it('selects weekly for > 90 days', () => {
    const from = new Date(2025, 0, 1)
    expect(intervalForRange(from, new Date(2025, 11, 31))).toBe('week')
  })
})

describe('useDateRangeStore extensions', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('exposes an apiRange of YYYY-MM-DD strings', () => {
    const store = useDateRangeStore()
    store.setCustomRange(new Date(2025, 5, 1), new Date(2025, 5, 30, 23, 59))
    expect(store.apiRange).toEqual({ date_from: '2025-06-01', date_to: '2025-06-30' })
  })

  it('derives the chart interval from the active range', () => {
    const store = useDateRangeStore()
    store.setCustomRange(new Date(2025, 0, 1), new Date(2025, 0, 2))
    expect(store.interval).toBe('hour')
  })

  it('only emits a compare value when comparison is enabled', () => {
    const store = useDateRangeStore()
    expect(store.compareValue).toBeUndefined()
    store.setComparePeriod('previous_year')
    expect(store.compareEnabled).toBe(true)
    expect(store.compareValue).toBe('previous_year')
  })

  it('toggleCompare flips the flag', () => {
    const store = useDateRangeStore()
    store.toggleCompare()
    expect(store.compareEnabled).toBe(true)
    store.toggleCompare()
    expect(store.compareEnabled).toBe(false)
  })
})
