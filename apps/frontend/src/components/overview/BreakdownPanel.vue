<script setup lang="ts">
// =============================================================================
// BreakdownPanel — top-N dimension list card (screens.md §1.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Renders one Overview breakdown card: a title, a ranked list of rows with an
// inline ProgressBar (data-viz.md §3.9), and an optional "View all" link. Pure
// presentation — the parent supplies already-shaped BreakdownListRow data and
// owns loading / error / empty state.
// =============================================================================
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Button from '@/components/ui/button/Button.vue'
import { buttonVariants } from '@/components/ui/button'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import { ArrowRight, RotateCcw } from 'lucide-vue-next'
import { countryFlag } from './countries'
import type { BreakdownListRow } from './breakdownTable'

withDefaults(
  defineProps<{
    title: string
    rows: BreakdownListRow[]
    /** Column header for the metric value (e.g. "Visitors"). */
    metricLabel: string
    loading?: boolean
    error?: boolean
    /** Skeleton placeholder count while loading. */
    skeletonRows?: number
    /** Show a leading flag glyph per row (countries list). */
    showFlag?: boolean
    /** Route the "View all" link targets; omit to hide the link. */
    viewAllTo?: string
  }>(),
  { loading: false, error: false, skeletonRows: 5, showFlag: false, viewAllTo: undefined },
)

const emit = defineEmits<{ retry: []; selectRow: [value: string] }>()

const { n } = useI18n()

function flagFor(row: BreakdownListRow): string {
  return countryFlag(row.value)
}
</script>

<template>
  <Card class="flex flex-col">
    <CardHeader :title="title" />

    <div class="min-h-0 flex-1">
      <!-- Loading -->
      <ul v-if="loading" class="flex flex-col gap-3" aria-hidden="true">
        <li v-for="i in skeletonRows" :key="`sk-${i}`" class="flex flex-col gap-1.5">
          <div class="flex items-center justify-between">
            <Skeleton class="h-3.5 w-1/2" />
            <Skeleton class="h-3.5 w-10" />
          </div>
          <Skeleton class="h-1 w-full" />
        </li>
      </ul>

      <!-- Error -->
      <EmptyState
        v-else-if="error"
        variant="error"
        :title="$t('overview.errors.loadFailed')"
        :description="$t('overview.errors.loadFailedHint')"
      >
        <template #action>
          <Button variant="outline" size="sm" @click="emit('retry')">
            <template #leading><RotateCcw class="size-4" aria-hidden="true" /></template>
            {{ $t('common.retry') }}
          </Button>
        </template>
      </EmptyState>

      <!-- Empty -->
      <EmptyState
        v-else-if="rows.length === 0"
        variant="no-data"
        :title="$t('common.noData')"
      />

      <!-- Loaded -->
      <ul v-else class="flex flex-col gap-3">
        <li
          v-for="row in rows"
          :key="row.value"
          class="flex flex-col gap-1.5"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-sm text-left outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
            @click="emit('selectRow', row.value)"
          >
            <span class="flex min-w-0 items-center gap-2">
              <span v-if="showFlag" class="shrink-0" aria-hidden="true">{{ flagFor(row) }}</span>
              <span class="truncate text-sm text-fg-primary">{{ row.label }}</span>
            </span>
            <span class="shrink-0 font-mono text-sm tabular-nums text-fg-secondary">
              {{ n(row.count, 'integer') }}
              <span class="ml-1 text-xs text-fg-muted">{{ n(row.sharePct / 100, 'percentWhole') }}</span>
            </span>
          </button>
          <ProgressBar :value="row.barPct" :max="100" :label="`${row.label}: ${metricLabel}`" />
        </li>
      </ul>
    </div>

    <template v-if="viewAllTo && !loading && !error && rows.length > 0">
      <div class="mt-4 flex justify-end">
        <RouterLink :to="viewAllTo" :class="buttonVariants({ variant: 'ghost', size: 'sm' })">
          {{ $t('common.viewAll') }}
          <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>
    </template>
  </Card>
</template>
