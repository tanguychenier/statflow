<script setup lang="ts">
// =============================================================================
// PagesSourcesTablePanel — one self-contained breakdown table (screens.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Composes the toolbar + table + row detail panel for a single dimension and
// wires them to a usePagesSourcesTable instance. Used directly for the simple
// tabs and once per UTM sub-tab. Owning its own composable means each table
// keeps independent search / sort / page state.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PagesSourcesToolbar from './PagesSourcesToolbar.vue'
import PagesSourcesTable from './PagesSourcesTable.vue'
import PageDetailPanel from './PageDetailPanel.vue'
import { usePagesSourcesTable } from './usePagesSourcesTable'
import { buildTableCsv, downloadCsv, type PagesSourcesRow } from './pagesSourcesTable'
import { METRIC_COLUMNS, type TabConfig } from './tabs'
import { displayLabel, utmDisplayLabel } from './labels'
import { toast } from '@/composables/useToast'
import { useDateRangeStore } from '@/stores/dateRange'
import type { Filter } from '@/api/types'

const props = withDefaults(
  defineProps<{
    /** Tab descriptor; for UTM sub-tabs an ad-hoc config is synthesised by the parent. */
    config: TabConfig
    filters: Filter[]
    /** Render values with the UTM "(none)" placeholder. */
    utm?: boolean
    /** Stamp used in the exported CSV filename. */
    exportSlug: string
  }>(),
  { utm: false },
)

const { t } = useI18n()
const dateRangeStore = useDateRangeStore()

const table = usePagesSourcesTable({
  property: () => props.config.property,
  filters: () => props.filters,
})

const dimensionHeader = computed(() => t(props.config.dimensionLabelKey))

const detailRow = ref<PagesSourcesRow | null>(null)
const detailOpen = ref(false)

function openDetail(row: PagesSourcesRow) {
  detailRow.value = row
  detailOpen.value = true
}

const detailLabel = computed(() => {
  if (!detailRow.value) return ''
  return props.utm
    ? utmDisplayLabel(detailRow.value.value, t)
    : displayLabel(detailRow.value.value, props.config.id, t)
})

const exporting = ref(false)
function onExport() {
  exporting.value = true
  try {
    const csv = buildTableCsv(
      table.allRows.value,
      dimensionHeader.value,
      METRIC_COLUMNS.map((column) => ({ metric: column.metric, header: t(column.labelKey) })),
      {
        search: table.search.value,
        sortMetric: table.metric.value,
        sortDirection: table.sortDirection.value,
      },
    )
    const stamp = `${dateRangeStore.apiRange.date_from}_${dateRangeStore.apiRange.date_to}`
    const ok = downloadCsv(`statflow-${props.exportSlug}-${stamp}.csv`, csv)
    if (ok) toast.success(t('pagesSources.export.success'))
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <PagesSourcesToolbar
      :search="table.search.value"
      :search-placeholder="t(config.searchPlaceholderKey)"
      :metric="table.metric.value"
      :exporting="exporting"
      @update:search="table.setSearch"
      @update:metric="table.setMetric"
      @export="onExport"
    />

    <PagesSourcesTable
      :rows="table.result.value.rows"
      :tab="config.id"
      :dimension-header="dimensionHeader"
      :sort="table.sortState.value"
      :page="table.result.value.page"
      :page-count="table.result.value.pageCount"
      :page-size="table.pageSize"
      :filtered-count="table.result.value.filteredCount"
      :total-count="table.result.value.totalCount"
      :loading="table.isLoading.value"
      :error="table.isError.value"
      :utm="utm"
      @update:sort="table.setSort"
      @update:page="table.setPage"
      @row-click="openDetail"
      @retry="table.refetch"
    />

    <PageDetailPanel
      :open="detailOpen"
      :row="detailRow"
      :tab="config"
      :label="detailLabel"
      @update:open="detailOpen = $event"
    />
  </div>
</template>
