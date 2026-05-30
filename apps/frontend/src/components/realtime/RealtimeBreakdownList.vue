<script setup lang="ts">
// =============================================================================
// RealtimeBreakdownList — ranked live list with FLIP reorder (screens.md §4.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A titled card listing ranked rows (top pages / sources / countries) with an
// inline ProgressBar. Rows reorder smoothly via Vue's <TransitionGroup> FLIP
// (move-class) when rankings change; the transition is disabled under reduced
// motion. Each row is keyed on a stable key so the FLIP can animate position.
// Purely presentational: parent supplies already-ranked rows + loading/error.
// =============================================================================
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import { useReducedMotion } from '@/composables/useReducedMotion'
import type { RankedRow } from './realtimeModel'

withDefaults(
  defineProps<{
    title: string
    rows: RankedRow[]
    /** Accessible name for the metric column (e.g. "Visitors"). */
    metricLabel: string
    /** Optional leading glyph per row (country flags). */
    leadingFor?: (row: RankedRow) => string
    loading?: boolean
    error?: boolean
    skeletonRows?: number
  }>(),
  { loading: false, error: false, skeletonRows: 5, leadingFor: undefined },
)

const emit = defineEmits<{ retry: [] }>()

const { n } = useI18n()
const { prefersReduced } = useReducedMotion()
</script>

<template>
  <Card class="flex flex-col">
    <CardHeader :title="title" />

    <div class="min-h-0 flex-1">
      <ul v-if="loading" class="flex flex-col gap-3" aria-hidden="true">
        <li v-for="i in skeletonRows" :key="`sk-${i}`" class="flex flex-col gap-1.5">
          <div class="flex items-center justify-between">
            <Skeleton class="h-3.5 w-1/2" />
            <Skeleton class="h-3.5 w-10" />
          </div>
          <Skeleton class="h-1 w-full" />
        </li>
      </ul>

      <EmptyState
        v-else-if="error"
        variant="error"
        :title="$t('realtime.errors.streamFailed')"
        :action-label="$t('common.retry')"
        @action="emit('retry')"
      />

      <EmptyState v-else-if="rows.length === 0" variant="no-data" :title="$t('common.noData')" />

      <TransitionGroup
        v-else
        tag="ul"
        :name="prefersReduced ? '' : 'rt-flip'"
        class="flex flex-col gap-3"
      >
        <li v-for="row in rows" :key="row.key" class="rt-row flex flex-col gap-1.5">
          <div class="flex items-center justify-between gap-3">
            <span class="flex min-w-0 items-center gap-2">
              <span v-if="leadingFor" class="shrink-0" aria-hidden="true">{{ leadingFor(row) }}</span>
              <span class="truncate text-sm text-fg-primary" :title="row.label">{{ row.label }}</span>
            </span>
            <span class="shrink-0 font-mono text-sm tabular-nums text-fg-secondary">
              {{ n(row.value, 'integer') }}
            </span>
          </div>
          <ProgressBar :value="row.barPct" :max="100" :label="`${row.label}: ${metricLabel}`" />
        </li>
      </TransitionGroup>
    </div>
  </Card>
</template>

<style scoped>
.rt-flip-move {
  transition: transform var(--sf-duration-slow) var(--sf-ease-in-out);
}

.rt-flip-enter-active,
.rt-flip-leave-active {
  transition:
    opacity var(--sf-duration-base) var(--sf-ease-out),
    transform var(--sf-duration-base) var(--sf-ease-out);
}

.rt-flip-enter-from,
.rt-flip-leave-to {
  opacity: 0;
}

/* Leaving rows are removed from flow so neighbours FLIP into place cleanly. */
.rt-flip-leave-active {
  position: absolute;
  width: 100%;
}

@media (prefers-reduced-motion: reduce) {
  .rt-flip-move,
  .rt-flip-enter-active,
  .rt-flip-leave-active {
    transition: none;
  }
}
</style>
