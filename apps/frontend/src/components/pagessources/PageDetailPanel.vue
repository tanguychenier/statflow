<script setup lang="ts">
// =============================================================================
// PageDetailPanel — row drill-down modal (screens.md §5.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Clicking a table row opens this panel: a mini time-series for the selected
// page/source (the global range filtered to that one dimension value) plus the
// row's full metric breakdown. The time-series query is owned here and keyed on
// the selected value, so it only fires while the panel is open.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import Modal from '@/components/ui/dialog/Modal.vue'
import { TimeSeriesChart } from '@/components/charts'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'
import { useTimeSeries } from '@/api/composables/useAnalytics'
import { formatDuration } from '@/utils/formatDuration'
import { METRIC_COLUMNS, type TabConfig } from './tabs'
import type { PagesSourcesRow } from './pagesSourcesTable'
import type { Filter, MetricName, TimeSeriesQuery } from '@/api/types'

const props = defineProps<{
  open: boolean
  row: PagesSourcesRow | null
  /** The active tab — its `property` becomes the time-series filter dimension. */
  tab: TabConfig
  /** Display label for the panel title (already resolved for direct/none). */
  label: string
}>()

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const { t, n } = useI18n()

const siteStore = useCurrentSiteStore()
const dateRangeStore = useDateRangeStore()
const { currentSiteId } = storeToRefs(siteStore)
const { apiRange, interval } = storeToRefs(dateRangeStore)

const rowFilter = computed<Filter[]>(() =>
  props.row ? [{ property: props.tab.property, operator: 'eq', value: props.row.value }] : [],
)

const seriesQuery = computed<TimeSeriesQuery>(() => ({
  ...apiRange.value,
  interval: interval.value,
  metrics: ['visitors'] as MetricName[],
  filters: rowFilter.value,
}))

// Only fetch while the panel is open for a concrete row.
const enabledSiteId = computed(() =>
  props.open && props.row ? currentSiteId.value : null,
)

const series = useTimeSeries(enabledSiteId, seriesQuery)

const chartData = computed(() => {
  const buckets = series.data.value?.series ?? []
  return {
    categories: buckets.map((bucket) => bucket.bucket),
    series: [
      {
        name: t('pagesSources.metrics.visitors'),
        data: buckets.map((bucket) => bucket.metrics.visitors ?? 0),
      },
    ],
  }
})

const breakdown = computed(() => {
  if (!props.row) return []
  return METRIC_COLUMNS.map((column) => {
    const value = props.row!.metrics[column.metric]
    let formatted: string
    if (column.format === 'percent') formatted = n(value / 100, 'percent')
    else if (column.format === 'duration') formatted = formatDuration(value)
    else formatted = n(value, 'integer')
    return { key: column.metric, label: t(column.labelKey), value: formatted }
  })
})
</script>

<template>
  <Modal
    :open="open"
    :title="label"
    size="md"
    :close-label="t('common.close')"
    @update:open="emit('update:open', $event)"
  >
    <template #header>
      <p class="mt-0.5 text-sm text-fg-secondary">{{ t(tab.dimensionLabelKey) }}</p>
    </template>

    <div class="flex flex-col gap-6">
      <section :aria-label="t('pagesSources.detail.trend')">
        <h3 class="mb-2 text-sm font-medium text-fg-secondary">
          {{ t('pagesSources.detail.trend') }}
        </h3>
        <TimeSeriesChart
          :title="t('pagesSources.detail.trend')"
          :categories="chartData.categories"
          :series="chartData.series"
          :loading="series.isPending.value"
          :error="series.isError.value"
          :stale="series.isFetching.value && !series.isPending.value"
          :height="200"
          :show-zoom="false"
          @retry="series.refetch()"
        />
      </section>

      <section :aria-label="t('pagesSources.detail.metrics')">
        <h3 class="mb-2 text-sm font-medium text-fg-secondary">
          {{ t('pagesSources.detail.metrics') }}
        </h3>
        <dl class="grid grid-cols-2 gap-3">
          <div
            v-for="item in breakdown"
            :key="item.key"
            class="rounded-lg border border-border bg-bg-subtle px-3 py-2"
          >
            <dt class="text-xs text-fg-muted">{{ item.label }}</dt>
            <dd class="mt-0.5 font-mono text-base tabular-nums text-fg-primary">{{ item.value }}</dd>
          </div>
        </dl>
      </section>
    </div>
  </Modal>
</template>
