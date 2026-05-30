<script setup lang="ts">
// =============================================================================
// PagesSourcesTable — feature-rich breakdown table (screens.md §5.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Wraps the design-system DataTable for one Pages & Sources dimension: a sticky
// dimension column carrying an inline bar (sized to the active metric) plus one
// sortable, right-aligned column per metric. Data, sort, search and pagination
// are owned by the parent view; this layer is presentation + formatting only.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import DataTable from '@/components/DataTable/DataTable.vue'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import Button from '@/components/ui/button/Button.vue'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import type { ColumnDef, SortState } from '@/components/DataTable/types'
import { formatDuration } from '@/utils/formatDuration'
import { METRIC_COLUMNS, type MetricColumn } from './tabs'
import type { PagesSourcesRow } from './pagesSourcesTable'
import { displayLabel, utmDisplayLabel } from './labels'
import type { PagesSourcesTab } from './tabs'

const props = withDefaults(
  defineProps<{
    rows: PagesSourcesRow[]
    /** Active tab — drives the dimension label and "direct/none" placeholders. */
    tab: PagesSourcesTab
    /** Header for the leading dimension column (already translated). */
    dimensionHeader: string
    sort: SortState
    page: number
    pageCount: number
    pageSize: number
    filteredCount: number
    totalCount: number
    loading?: boolean
    error?: boolean
    /** Render values with the UTM "(none)" placeholder regardless of tab. */
    utm?: boolean
  }>(),
  { loading: false, error: false, utm: false },
)

const emit = defineEmits<{
  'update:sort': [value: SortState]
  'update:page': [value: number]
  rowClick: [row: PagesSourcesRow]
  retry: []
}>()

const { t, n } = useI18n()

const columns = computed<ColumnDef<PagesSourcesRow & Record<string, unknown>>[]>(() => [
  { key: 'value', header: props.dimensionHeader, sortable: true },
  ...METRIC_COLUMNS.map((column) => ({
    key: column.metric,
    header: t(column.labelKey),
    numeric: true,
    sortable: true,
    accessor: (row: PagesSourcesRow) => row.metrics[column.metric],
  })),
])

// DataTable sorts by column `key`; metric columns use the metric name as key, so
// the SortState round-trips directly to the parent's metric/direction state.
const tableSort = computed<SortState>(() => props.sort)

function onSort(next: SortState) {
  emit('update:sort', next)
}

function labelFor(row: PagesSourcesRow): string {
  return props.utm ? utmDisplayLabel(row.value, t) : displayLabel(row.value, props.tab, t)
}

function formatMetric(column: MetricColumn, value: number): string {
  if (column.format === 'percent') return n(value / 10000, 'percent')
  if (column.format === 'duration') return formatDuration(value)
  return n(value, 'integer')
}

const tableRows = computed(
  () => props.rows as Array<PagesSourcesRow & Record<string, unknown>>,
)

const rangeStart = computed(() =>
  props.filteredCount === 0 ? 0 : (props.page - 1) * props.pageSize + 1,
)
const rangeEnd = computed(() => Math.min(props.page * props.pageSize, props.filteredCount))

const canPrev = computed(() => props.page > 1)
const canNext = computed(() => props.page < props.pageCount)

const metricColumns = METRIC_COLUMNS
</script>

<template>
  <EmptyState
    v-if="error"
    variant="error"
    :title="t('pagesSources.errors.loadFailed')"
    :description="t('pagesSources.errors.loadFailedHint')"
  >
    <template #action>
      <Button variant="outline" size="sm" @click="emit('retry')">
        {{ t('common.retry') }}
      </Button>
    </template>
  </EmptyState>

  <DataTable
    v-else
    :columns="columns"
    :rows="tableRows"
    row-key="value"
    :sort="tableSort"
    :loading="loading"
    row-interactive
    :empty-title="t('pagesSources.empty.title')"
    :empty-description="t('pagesSources.empty.description')"
    @update:sort="onSort"
    @row-click="emit('rowClick', $event as PagesSourcesRow)"
  >
    <!-- Dimension cell: clickable label + leader-relative inline bar -->
    <template #[`cell:value`]="{ row }">
      <div class="flex min-w-0 flex-col gap-1 py-1">
        <span class="truncate text-fg-primary" :title="labelFor(row as PagesSourcesRow)">
          {{ labelFor(row as PagesSourcesRow) }}
        </span>
        <ProgressBar
          :value="(row as PagesSourcesRow).barPct"
          :max="100"
          :label="`${labelFor(row as PagesSourcesRow)}: ${dimensionHeader}`"
        />
      </div>
    </template>

    <!-- One formatted, right-aligned cell per metric -->
    <template
      v-for="column in metricColumns"
      :key="column.metric"
      #[`cell:${column.metric}`]="{ row }"
    >
      {{ formatMetric(column, (row as PagesSourcesRow).metrics[column.metric]) }}
    </template>

    <template #pagination>
      <div
        v-if="filteredCount > 0"
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-fg-secondary"
      >
        <p>
          {{
            t('pagesSources.pagination.range', {
              start: n(rangeStart, 'integer'),
              end: n(rangeEnd, 'integer'),
              total: n(filteredCount, 'integer'),
            })
          }}
        </p>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="!canPrev"
            :aria-label="t('pagesSources.pagination.prev')"
            @click="emit('update:page', page - 1)"
          >
            <template #leading><ChevronLeft class="size-4" aria-hidden="true" /></template>
            {{ t('pagesSources.pagination.prev') }}
          </Button>
          <span class="tabular-nums">
            {{ t('pagesSources.pagination.page', { page: n(page, 'integer'), pageCount: n(pageCount, 'integer') }) }}
          </span>
          <Button
            variant="outline"
            size="sm"
            :disabled="!canNext"
            :aria-label="t('pagesSources.pagination.next')"
            @click="emit('update:page', page + 1)"
          >
            {{ t('pagesSources.pagination.next') }}
            <template #trailing><ChevronRight class="size-4" aria-hidden="true" /></template>
          </Button>
        </div>
      </div>
    </template>
  </DataTable>
</template>
