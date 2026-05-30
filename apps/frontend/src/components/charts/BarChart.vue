<script setup lang="ts">
// =============================================================================
// BarChart — horizontal/vertical bar for top-N lists (data-viz.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import ChartWrapper from './ChartWrapper.vue'
import { buildBarOption } from '@/charts/options'
import { useChartFormat } from '@/composables/useChartFormat'

const props = withDefaults(
  defineProps<{
    title: string
    categories: string[]
    values: number[]
    horizontal?: boolean
    loading?: boolean
    error?: boolean
    stale?: boolean
    height?: number
  }>(),
  { horizontal: true, loading: false, error: false, stale: false, height: 280 },
)

const emit = defineEmits<{ retry: []; click: [params: unknown] }>()

const { formatters } = useChartFormat()
const isEmpty = computed(() => props.values.length === 0)
const option = computed(() =>
  buildBarOption(props.categories, props.values, formatters.value, { horizontal: props.horizontal }),
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
    @click="emit('click', $event)"
  >
    <template v-if="$slots.table" #table><slot name="table" /></template>
  </ChartWrapper>
</template>
