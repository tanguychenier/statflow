<script setup lang="ts">
// =============================================================================
// TimeSeriesChart — area line over time (data-viz.md §3.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import ChartWrapper from './ChartWrapper.vue'
import { buildTimeSeriesOption, type SeriesInput } from '@/charts/options'
import { useChartFormat } from '@/composables/useChartFormat'

const props = withDefaults(
  defineProps<{
    title: string
    categories: string[]
    series: SeriesInput[]
    loading?: boolean
    error?: boolean
    stale?: boolean
    showZoom?: boolean
    height?: number
  }>(),
  { loading: false, error: false, stale: false, showZoom: true, height: 320 },
)

const emit = defineEmits<{ retry: []; dataZoom: [params: unknown] }>()

const { formatters } = useChartFormat()

const isEmpty = computed(
  () => props.series.length === 0 || props.series.every((s) => s.data.every((v) => v === 0)),
)

const option = computed(() =>
  buildTimeSeriesOption(props.categories, props.series, formatters.value, {
    showZoom: props.showZoom,
  }),
)
</script>

<template>
  <ChartWrapper
    :title="title"
    :option="option"
    :loading="loading"
    :error="error"
    :empty="isEmpty"
    :stale="stale"
    :height="height"
    @retry="emit('retry')"
    @data-zoom="emit('dataZoom', $event)"
  >
    <template v-if="$slots.table" #table><slot name="table" /></template>
  </ChartWrapper>
</template>
