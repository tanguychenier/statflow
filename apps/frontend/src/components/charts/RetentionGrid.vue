<script setup lang="ts">
// =============================================================================
// RetentionGrid — cohort heatmap (data-viz.md §3.4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Accepts the API RetentionResponse shape and reshapes it into the ECharts
// heatmap cell triples [periodIndex, cohortIndex, retentionPct].
// =============================================================================
import { computed } from 'vue'
import ChartWrapper from './ChartWrapper.vue'
import { buildRetentionOption } from '@/charts/options'
import { useChartFormat } from '@/composables/useChartFormat'
import type { RetentionCohort } from '@/api/types'

const props = withDefaults(
  defineProps<{
    title: string
    cohorts: RetentionCohort[]
    loading?: boolean
    error?: boolean
    height?: number
  }>(),
  { loading: false, error: false, height: 360 },
)

const emit = defineEmits<{ retry: [] }>()
const { formatters } = useChartFormat()

const isEmpty = computed(() => props.cohorts.length === 0)

const periodLabels = computed(() => {
  const maxPeriods = props.cohorts.reduce((max, c) => Math.max(max, c.retention.length), 0)
  return Array.from({ length: maxPeriods }, (_, i) => String(i))
})

const cohortLabels = computed(() => props.cohorts.map((c) => c.cohort_date))

const cells = computed<Array<[number, number, number]>>(() =>
  props.cohorts.flatMap((cohort, cohortIndex) =>
    cohort.retention.map(
      (point) => [point.period, cohortIndex, point.retention_pct] as [number, number, number],
    ),
  ),
)

const option = computed(() =>
  buildRetentionOption(cohortLabels.value, periodLabels.value, cells.value, formatters.value),
)
</script>

<template>
  <ChartWrapper
    :title="title"
    :option="option"
    :loading="loading"
    :error="error"
    :empty="isEmpty"
    :height="height"
    @retry="emit('retry')"
  >
    <template v-if="$slots.table" #table><slot name="table" /></template>
  </ChartWrapper>
</template>
