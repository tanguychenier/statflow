<script setup lang="ts" generic="Row extends Record<string, unknown>">
// =============================================================================
// DataTable — feature-rich server-side data table (components.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Presentational + headless: the parent owns data, pagination and fetching
// (via @tanstack/vue-query) and reacts to `update:sort` / `update:selection`.
// Supports sorting (Shift adds secondary intent via emit), row selection with a
// header tri-state checkbox, three densities, a sticky first column, monospace
// numeric cells, skeleton loading rows, and an integrated empty state.
// =============================================================================
import { computed } from 'vue'
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-vue-next'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import { cn } from '@/lib/utils'
import type { ColumnDef, Density, SortState } from './types'

const props = withDefaults(
  defineProps<{
    columns: ColumnDef<Row>[]
    rows: Row[]
    rowKey: keyof Row | ((row: Row) => string)
    loading?: boolean
    density?: Density
    selectable?: boolean
    selection?: string[]
    sort?: SortState | null
    skeletonRows?: number
    emptyTitle?: string
    emptyDescription?: string
    /** Whole-row click opens a detail action — exposes rows as keyboard buttons. */
    rowInteractive?: boolean
  }>(),
  {
    loading: false,
    density: 'default',
    selectable: false,
    selection: () => [],
    sort: null,
    skeletonRows: 10,
    rowInteractive: false,
  },
)

const emit = defineEmits<{
  'update:sort': [value: SortState]
  'update:selection': [value: string[]]
  rowClick: [row: Row]
}>()

const ROW_HEIGHT: Record<Density, string> = {
  compact: 'h-8',
  default: 'h-10',
  relaxed: 'h-12',
}

function keyOf(row: Row): string {
  return typeof props.rowKey === 'function' ? props.rowKey(row) : String(row[props.rowKey])
}

function cellValue(row: Row, column: ColumnDef<Row>): unknown {
  return column.accessor ? column.accessor(row) : row[column.key]
}

const allSelected = computed(
  () => props.rows.length > 0 && props.selection.length === props.rows.length,
)
const someSelected = computed(
  () => props.selection.length > 0 && props.selection.length < props.rows.length,
)
const headerCheckState = computed<boolean | 'indeterminate'>(() =>
  someSelected.value ? 'indeterminate' : allSelected.value,
)

function toggleAll() {
  emit('update:selection', allSelected.value ? [] : props.rows.map(keyOf))
}

function toggleRow(row: Row) {
  const id = keyOf(row)
  const next = props.selection.includes(id)
    ? props.selection.filter((key) => key !== id)
    : [...props.selection, id]
  emit('update:selection', next)
}

function onSort(column: ColumnDef<Row>) {
  if (!column.sortable) return
  const direction =
    props.sort?.key === column.key && props.sort.direction === 'desc' ? 'asc' : 'desc'
  emit('update:sort', { key: column.key, direction })
}

function activateRow(row: Row) {
  emit('rowClick', row)
}

// Keyboard parity for the whole-row action: Enter and Space activate, Space is
// prevented so it does not scroll the table viewport (accessibility.md §3.3).
function onRowKeydown(event: KeyboardEvent, row: Row) {
  if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
    event.preventDefault()
    activateRow(row)
  }
}

const isEmpty = computed(() => !props.loading && props.rows.length === 0)
</script>

<template>
  <div class="sf-table flex flex-col gap-3">
    <div v-if="$slots.toolbar" class="sf-table__toolbar">
      <slot name="toolbar" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-border">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="border-b border-border bg-bg-subtle">
            <th v-if="selectable" class="sticky left-0 z-10 w-10 bg-bg-subtle px-3">
              <Checkbox
                :model-value="headerCheckState"
                aria-label="Select all rows"
                @update:model-value="toggleAll"
              />
            </th>
            <th
              v-for="(column, colIndex) in columns"
              :key="column.key"
              scope="col"
              :aria-sort="
                sort?.key === column.key ? (sort.direction === 'asc' ? 'ascending' : 'descending') : undefined
              "
              :class="
                cn(
                  'px-3 py-2 font-medium text-fg-secondary',
                  column.numeric ? 'text-right' : 'text-left',
                  colIndex === 0 && 'sticky left-0 z-10 bg-bg-subtle',
                  selectable && colIndex === 0 && 'left-10',
                )
              "
              :style="column.width ? { width: column.width } : undefined"
            >
              <button
                v-if="column.sortable"
                type="button"
                class="inline-flex items-center gap-1 hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus rounded-sm"
                :class="column.numeric && 'flex-row-reverse'"
                @click="onSort(column)"
              >
                {{ column.header }}
                <ArrowUp v-if="sort?.key === column.key && sort.direction === 'asc'" class="size-3" aria-hidden="true" />
                <ArrowDown v-else-if="sort?.key === column.key" class="size-3" aria-hidden="true" />
                <ChevronsUpDown v-else class="size-3 opacity-50" aria-hidden="true" />
              </button>
              <span v-else>{{ column.header }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="i in skeletonRows" :key="`sk-${i}`" :class="ROW_HEIGHT[density]">
              <td v-if="selectable" class="px-3"><Skeleton class="size-4" /></td>
              <td v-for="column in columns" :key="column.key" class="px-3">
                <Skeleton class="h-3.5 w-3/4" />
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="row in rows"
              :key="keyOf(row)"
              :tabindex="rowInteractive ? 0 : undefined"
              :aria-label="rowInteractive ? keyOf(row) : undefined"
              :class="
                cn(
                  ROW_HEIGHT[density],
                  'border-b border-border last:border-0 transition-colors hover:bg-bg-subtle',
                  rowInteractive &&
                    'cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-border-focus',
                )
              "
              @click="activateRow(row)"
              @keydown="rowInteractive && onRowKeydown($event, row)"
            >
              <td v-if="selectable" class="sticky left-0 z-10 bg-bg-surface px-3" @click.stop>
                <Checkbox
                  :model-value="selection.includes(keyOf(row))"
                  :aria-label="`Select row ${keyOf(row)}`"
                  @update:model-value="toggleRow(row)"
                />
              </td>
              <td
                v-for="(column, colIndex) in columns"
                :key="column.key"
                :class="
                  cn(
                    'px-3 text-fg-primary',
                    column.numeric ? 'text-right font-mono tabular-nums' : 'text-left',
                    colIndex === 0 && 'sticky left-0 z-10 bg-bg-surface',
                    selectable && colIndex === 0 && 'left-10',
                  )
                "
              >
                <slot :name="`cell:${column.key}`" :row="row" :value="cellValue(row, column)">
                  {{ cellValue(row, column) }}
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <EmptyState
        v-if="isEmpty"
        variant="no-results"
        :title="emptyTitle ?? $t('common.noData')"
        :description="emptyDescription"
      />
    </div>

    <div v-if="$slots.pagination" class="sf-table__pagination">
      <slot name="pagination" />
    </div>
  </div>
</template>
