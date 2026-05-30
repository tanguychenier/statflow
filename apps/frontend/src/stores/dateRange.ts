// =============================================================================
// Statflow Dashboard — Date Range Store
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { ComparePeriod, TimeInterval } from '@/api/types'

export type DateRangePreset =
  | 'today'
  | 'yesterday'
  | 'last7Days'
  | 'last30Days'
  | 'last90Days'
  | 'thisMonth'
  | 'lastMonth'
  | 'custom'

export interface DateRange {
  from: Date
  to: Date
}

/** Format a Date as a `YYYY-MM-DD` string in local time (no UTC shift). */
export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * Pick the chart bucket size from the span of the range, per data-viz.md §3.1:
 * ≤ 2 days → hourly; ≤ 90 days → daily; > 90 days → weekly.
 */
export function intervalForRange(from: Date, to: Date): TimeInterval {
  const spanDays = Math.ceil((to.getTime() - from.getTime()) / 86_400_000)
  if (spanDays <= 2) return 'hour'
  if (spanDays <= 90) return 'day'
  return 'week'
}

function getLast30Days(): DateRange {
  const to = new Date()
  to.setHours(23, 59, 59, 999)
  const from = new Date()
  from.setDate(from.getDate() - 29)
  from.setHours(0, 0, 0, 0)
  return { from, to }
}

export const useDateRangeStore = defineStore('dateRange', () => {
  const preset = ref<DateRangePreset>('last30Days')
  const range = ref<DateRange>(getLast30Days())
  const compareEnabled = ref(false)
  const comparePeriod = ref<ComparePeriod>('previous_period')

  const label = computed(() => {
    // Returns an ISO date string pair for cache key usage
    return `${range.value.from.toISOString()}_${range.value.to.toISOString()}`
  })

  /** `{ date_from, date_to }` ready to spread into an analytics query body. */
  const apiRange = computed(() => ({
    date_from: toIsoDate(range.value.from),
    date_to: toIsoDate(range.value.to),
  }))

  /** Auto-selected chart bucket size for the active range. */
  const interval = computed<TimeInterval>(() =>
    intervalForRange(range.value.from, range.value.to),
  )

  /** The compare_period value to send, or undefined when comparison is off. */
  const compareValue = computed<ComparePeriod | undefined>(() =>
    compareEnabled.value ? comparePeriod.value : undefined,
  )

  function setPreset(next: DateRangePreset) {
    preset.value = next
    if (next !== 'custom') {
      range.value = resolvePreset(next)
    }
  }

  function setCustomRange(from: Date, to: Date) {
    preset.value = 'custom'
    range.value = { from, to }
  }

  function toggleCompare() {
    compareEnabled.value = !compareEnabled.value
  }

  function setComparePeriod(next: ComparePeriod) {
    comparePeriod.value = next
    compareEnabled.value = true
  }

  return {
    preset,
    range,
    compareEnabled,
    comparePeriod,
    label,
    apiRange,
    interval,
    compareValue,
    setPreset,
    setCustomRange,
    toggleCompare,
    setComparePeriod,
  }
})

function resolvePreset(preset: Exclude<DateRangePreset, 'custom'>): DateRange {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  switch (preset) {
    case 'today':
      return { from: today, to: now }
    case 'yesterday': {
      const yStart = new Date(today)
      yStart.setDate(yStart.getDate() - 1)
      const yEnd = new Date(yStart)
      yEnd.setHours(23, 59, 59, 999)
      return { from: yStart, to: yEnd }
    }
    case 'last7Days': {
      const from = new Date(today)
      from.setDate(from.getDate() - 6)
      return { from, to: now }
    }
    case 'last30Days':
      return getLast30Days()
    case 'last90Days': {
      const from = new Date(today)
      from.setDate(from.getDate() - 89)
      return { from, to: now }
    }
    case 'thisMonth': {
      const from = new Date(now.getFullYear(), now.getMonth(), 1)
      return { from, to: now }
    }
    case 'lastMonth': {
      const from = new Date(now.getFullYear(), now.getMonth() - 1, 1)
      const to = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59, 999)
      return { from, to }
    }
  }
}
