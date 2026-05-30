<script setup lang="ts">
// =============================================================================
// FunnelChart — stepped conversion bars (data-viz.md §3.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Deliberately NOT the ECharts native funnel (too decorative for Statflow's
// data-dense aesthetic). Renders horizontal bars sized by conversion-from-entry
// with drop-off annotations between steps. Pure CSS keeps it crisp at any width,
// keyboard-navigable, and trivially screen-reader friendly via a real list.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ChartEmpty from './ChartEmpty.vue'
import ChartSkeleton from './ChartSkeleton.vue'
import type { FunnelResponseStep } from '@/api/types'

const props = withDefaults(
  defineProps<{ steps: FunnelResponseStep[]; loading?: boolean; error?: boolean }>(),
  { loading: false, error: false },
)

const emit = defineEmits<{ retry: [] }>()
const { n } = useI18n()

const entryCount = computed(() => props.steps[0]?.entered ?? 0)

interface Row {
  index: number
  label: string
  entered: number
  widthPct: number
  fromEntryPct: number
  dropPct: number | null
}

const rows = computed<Row[]>(() =>
  props.steps.map((step, i) => {
    const fromEntry = entryCount.value > 0 ? (step.entered / entryCount.value) * 100 : 0
    const prev = props.steps[i - 1]
    const dropPct =
      prev && prev.entered > 0 ? ((prev.entered - step.entered) / prev.entered) * 100 : null
    return {
      index: step.step_index,
      label: step.label,
      entered: step.entered,
      widthPct: Math.max(2, fromEntry),
      fromEntryPct: fromEntry,
      dropPct,
    }
  }),
)

const isEmpty = computed(() => props.steps.length === 0)

/** Intl percent for the standalone "% of entry" column (value is 0–100). */
function pct(value: number): string {
  return n(value / 100, 'percentWhole')
}

/** Bare one-decimal number for the "{pct}% dropped" i18n template. */
function pctNumber(value: number): string {
  return n(value, 'decimal')
}
</script>

<template>
  <ChartSkeleton v-if="loading" :height="320" />
  <ChartEmpty v-else-if="error" variant="error" @retry="emit('retry')" />
  <ChartEmpty v-else-if="isEmpty" variant="no-data" />
  <ol v-else class="sf-funnel flex flex-col gap-1">
    <li v-for="row in rows" :key="row.index" class="sf-funnel__step">
      <p
        v-if="row.dropPct !== null"
        class="mb-1 ml-1 text-xs text-negative-text"
        aria-hidden="true"
      >
        ↓ {{ $t('funnels.dropped', { pct: pctNumber(row.dropPct) }) }}
      </p>
      <div class="flex items-center gap-3">
        <div class="relative h-9 flex-1 overflow-hidden rounded-md bg-bg-subtle">
          <div
            class="flex h-full items-center rounded-md bg-accent px-3 text-sm font-medium text-white transition-[width]"
            :style="{ width: `${row.widthPct}%` }"
          >
            <span class="truncate">{{ row.label }}</span>
          </div>
        </div>
        <span class="w-20 shrink-0 text-right font-mono text-sm text-fg-primary">
          {{ n(row.entered, 'integer') }}
        </span>
        <span class="w-14 shrink-0 text-right text-sm text-fg-secondary">
          {{ pct(row.fromEntryPct) }}
        </span>
      </div>
    </li>
  </ol>
</template>
