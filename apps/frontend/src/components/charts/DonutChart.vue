<script setup lang="ts">
// =============================================================================
// DonutChart — device/browser ring split (data-viz.md §3.7)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ChartWrapper from './ChartWrapper.vue'
import { buildDonutOption } from '@/charts/options'
import { useChartFormat } from '@/composables/useChartFormat'

const props = withDefaults(
  defineProps<{
    title: string
    slices: Array<{ name: string; value: number }>
    loading?: boolean
    error?: boolean
    stale?: boolean
    height?: number
  }>(),
  { loading: false, error: false, stale: false, height: 240 },
)

const emit = defineEmits<{ retry: [] }>()

const { t } = useI18n()
const { formatters } = useChartFormat()
const isEmpty = computed(() => props.slices.length === 0 || props.slices.every((s) => s.value === 0))
const option = computed(() => buildDonutOption(props.slices, formatters.value, t('charts.other')))
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
  >
    <template v-if="$slots.table" #table><slot name="table" /></template>
  </ChartWrapper>
</template>
