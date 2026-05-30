// =============================================================================
// usePagesSourcesTable — per-table state, query and shaping (screens.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// One instance backs one breakdown table (a tab, or a UTM sub-tab). It owns the
// table-local UI state (search / metric / sort / page), issues the breakdown
// query keyed on the dimension + global range + active filters, and exposes the
// already-shaped page of rows. Tab switching mounts a fresh instance so each
// table keeps its own sort/search/page without leaking across tabs.
// =============================================================================

import { computed, ref, watch, type MaybeRefOrGetter, toValue } from 'vue'
import { storeToRefs } from 'pinia'
import { useBreakdown } from '@/api/composables/useAnalytics'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'
import type { BreakdownProperty, BreakdownQuery, Filter, MetricName } from '@/api/types'
import type { SortDirection, SortState } from '@/components/DataTable/types'
import { REQUESTED_METRICS } from './tabs'
import { toRows, shapeRows } from './pagesSourcesTable'

const DEFAULT_METRIC: MetricName = 'visitors'
const DEFAULT_PAGE_SIZE = 25
// Request a generous page from the API; client-side search/sort/paginate operate
// on this set, matching the screen spec's "client filter for visible data".
const FETCH_LIMIT = 500

export interface UsePagesSourcesTableOptions {
  property: MaybeRefOrGetter<BreakdownProperty>
  filters: MaybeRefOrGetter<Filter[]>
  pageSize?: number
}

export function usePagesSourcesTable(options: UsePagesSourcesTableOptions) {
  const siteStore = useCurrentSiteStore()
  const dateRangeStore = useDateRangeStore()
  const { currentSiteId } = storeToRefs(siteStore)
  const { apiRange } = storeToRefs(dateRangeStore)

  const pageSize = options.pageSize ?? DEFAULT_PAGE_SIZE

  const search = ref('')
  const metric = ref<MetricName>(DEFAULT_METRIC)
  const sortDirection = ref<SortDirection>('desc')
  const page = ref(1)

  const query = computed<BreakdownQuery>(() => ({
    ...apiRange.value,
    property: toValue(options.property),
    metrics: REQUESTED_METRICS,
    filters: toValue(options.filters),
    limit: FETCH_LIMIT,
    sort_by: metric.value,
    sort_order: sortDirection.value,
  }))

  const breakdown = useBreakdown(currentSiteId, query)

  const allRows = computed(() => toRows(breakdown.data.value))

  const result = computed(() =>
    shapeRows(allRows.value, {
      search: search.value,
      sortMetric: metric.value,
      sortDirection: sortDirection.value,
      page: page.value,
      pageSize,
    }),
  )

  const sortState = computed<SortState>(() => ({ key: metric.value, direction: sortDirection.value }))

  function setSort(next: SortState) {
    // The dimension column sorts on the active metric but flips direction; metric
    // columns switch the active metric (and thus the inline bar).
    if (next.key === 'value') {
      sortDirection.value = next.direction
    } else {
      metric.value = next.key as MetricName
      sortDirection.value = next.direction
    }
    page.value = 1
  }

  function setMetric(next: MetricName) {
    metric.value = next
    page.value = 1
  }

  function setSearch(next: string) {
    search.value = next
    page.value = 1
  }

  function setPage(next: number) {
    page.value = next
  }

  // Reset to the first page whenever the underlying data set changes (range,
  // site or filters) so the user never lands on an out-of-range page.
  watch(
    () => [apiRange.value.date_from, apiRange.value.date_to, currentSiteId.value, toValue(options.filters)],
    () => {
      page.value = 1
    },
    { deep: true },
  )

  return {
    search,
    metric,
    sortDirection,
    page,
    pageSize,
    sortState,
    result,
    allRows,
    isLoading: computed(() => breakdown.isPending.value),
    isError: computed(() => breakdown.isError.value),
    refetch: () => breakdown.refetch(),
    setSort,
    setMetric,
    setSearch,
    setPage,
  }
}
