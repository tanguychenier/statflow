// =============================================================================
// usePagesSourcesTable — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The breakdown query is mocked so the test exercises the composable's local
// state machine: sort toggling, metric switching, search and pagination resets,
// and the query body it builds. Coverage target: ≥90% lines + branches.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, type Ref } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import type { BreakdownQuery, BreakdownResponse } from '@/api/types'

const breakdownData = ref<BreakdownResponse | null>(null)
const lastQuery: Ref<BreakdownQuery | null> = ref(null)
const refetchSpy = vi.fn()

vi.mock('@/api/composables/useAnalytics', () => ({
  useBreakdown: (_siteId: unknown, query: { value: BreakdownQuery }) => {
    // Capture the reactive query so assertions can read the request body.
    lastQuery.value = query.value
    return {
      data: breakdownData,
      isPending: ref(false),
      isError: ref(false),
      isFetching: ref(false),
      refetch: refetchSpy,
    }
  },
}))

import { usePagesSourcesTable } from '@/components/pagessources/usePagesSourcesTable'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'

function setup(filters = ref<[]>([])) {
  return usePagesSourcesTable({ property: () => 'pathname', filters: () => filters.value })
}

function seed() {
  breakdownData.value = {
    property: 'pathname',
    period: { from: '2024-12-01', to: '2024-12-31' },
    total_rows: 3,
    rows: [
      { value: '/pricing', metrics: { visitors: 100, pageviews: 200 } },
      { value: '/docs', metrics: { visitors: 80, pageviews: 400 } },
      { value: '/blog', metrics: { visitors: 50, pageviews: 60 } },
    ],
  }
}

describe('usePagesSourcesTable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useCurrentSiteStore().selectSite('s1')
    seed()
    refetchSpy.mockClear()
  })

  it('builds a breakdown query for the dimension with all metrics', () => {
    setup()
    expect(lastQuery.value?.property).toBe('pathname')
    expect(lastQuery.value?.metrics).toContain('visitors')
    expect(lastQuery.value?.sort_by).toBe('visitors')
    expect(lastQuery.value?.sort_order).toBe('desc')
  })

  it('shapes the breakdown into a sorted page of rows', () => {
    const table = setup()
    expect(table.result.value.rows.map((r) => r.value)).toEqual(['/pricing', '/docs', '/blog'])
    expect(table.result.value.totalCount).toBe(3)
  })

  it('switches the active metric and resets to page 1', () => {
    const table = setup()
    table.setPage(2)
    table.setMetric('pageviews')
    expect(table.metric.value).toBe('pageviews')
    expect(table.page.value).toBe(1)
    expect(table.result.value.rows[0].value).toBe('/docs')
  })

  it('flips direction when sorting on the dimension column', () => {
    const table = setup()
    table.setSort({ key: 'value', direction: 'asc' })
    expect(table.metric.value).toBe('visitors')
    expect(table.sortDirection.value).toBe('asc')
  })

  it('changes the active metric when sorting on a metric column', () => {
    const table = setup()
    table.setSort({ key: 'pageviews', direction: 'desc' })
    expect(table.metric.value).toBe('pageviews')
  })

  it('filters client-side on search and resets the page', () => {
    const table = setup()
    table.setPage(2)
    table.setSearch('doc')
    expect(table.page.value).toBe(1)
    expect(table.result.value.rows.map((r) => r.value)).toEqual(['/docs'])
  })

  it('exposes a refetch passthrough', () => {
    const table = setup()
    table.refetch()
    expect(refetchSpy).toHaveBeenCalled()
  })

  it('resets to page 1 when the global date range changes', async () => {
    const table = setup()
    table.setPage(2)
    expect(table.page.value).toBe(2)
    useDateRangeStore().setPreset('last7Days')
    await Promise.resolve()
    expect(table.page.value).toBe(1)
  })
})
