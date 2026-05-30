// =============================================================================
// Date Range Store — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useDateRangeStore } from '@/stores/dateRange'

describe('useDateRangeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('defaults to last30Days preset', () => {
    const store = useDateRangeStore()
    expect(store.preset).toBe('last30Days')
  })

  it('has a valid initial date range', () => {
    const store = useDateRangeStore()
    expect(store.range.from).toBeInstanceOf(Date)
    expect(store.range.to).toBeInstanceOf(Date)
    expect(store.range.from < store.range.to).toBe(true)
  })

  it('compareEnabled defaults to false', () => {
    const store = useDateRangeStore()
    expect(store.compareEnabled).toBe(false)
  })

  it('toggleCompare enables comparison', () => {
    const store = useDateRangeStore()
    store.toggleCompare()
    expect(store.compareEnabled).toBe(true)
  })

  it('toggleCompare disables comparison when already enabled', () => {
    const store = useDateRangeStore()
    store.toggleCompare()
    store.toggleCompare()
    expect(store.compareEnabled).toBe(false)
  })

  it('setPreset updates the preset', () => {
    const store = useDateRangeStore()
    store.setPreset('today')
    expect(store.preset).toBe('today')
  })

  it('setPreset resolves today range (from <= to)', () => {
    const store = useDateRangeStore()
    store.setPreset('today')
    expect(store.range.from <= store.range.to).toBe(true)
  })

  it('setPreset resolves yesterday range', () => {
    const store = useDateRangeStore()
    store.setPreset('yesterday')
    expect(store.preset).toBe('yesterday')
    expect(store.range.from < store.range.to).toBe(true)
  })

  it('setPreset resolves last7Days (from is 6 days before today)', () => {
    const store = useDateRangeStore()
    store.setPreset('last7Days')
    // 7 days = today + 6 previous days. The from date should be 6 days before today.
    const today = new Date()
    const expectedFrom = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6)
    expect(store.range.from.getFullYear()).toBe(expectedFrom.getFullYear())
    expect(store.range.from.getMonth()).toBe(expectedFrom.getMonth())
    expect(store.range.from.getDate()).toBe(expectedFrom.getDate())
  })

  it('setPreset resolves last90Days', () => {
    const store = useDateRangeStore()
    store.setPreset('last90Days')
    expect(store.preset).toBe('last90Days')
  })

  it('setPreset resolves thisMonth', () => {
    const store = useDateRangeStore()
    store.setPreset('thisMonth')
    expect(store.range.from.getDate()).toBe(1)
  })

  it('setPreset resolves lastMonth (from = 1st of previous month)', () => {
    const store = useDateRangeStore()
    store.setPreset('lastMonth')
    expect(store.range.from.getDate()).toBe(1)
  })

  it('setCustomRange sets custom preset and date', () => {
    const store = useDateRangeStore()
    const from = new Date('2024-12-01')
    const to = new Date('2024-12-31')
    store.setCustomRange(from, to)
    expect(store.preset).toBe('custom')
    expect(store.range.from).toEqual(from)
    expect(store.range.to).toEqual(to)
  })

  it('label is a non-empty string', () => {
    const store = useDateRangeStore()
    expect(typeof store.label).toBe('string')
    expect(store.label.length).toBeGreaterThan(0)
  })

  it('label changes when range changes', () => {
    const store = useDateRangeStore()
    const initialLabel = store.label
    store.setPreset('today')
    // Labels may differ depending on time; just ensure it's a string
    expect(typeof store.label).toBe('string')
    // If we set a fixed custom range, the label should differ from the default
    store.setCustomRange(new Date('2020-01-01'), new Date('2020-01-31'))
    expect(store.label).not.toBe(initialLabel)
  })
})
