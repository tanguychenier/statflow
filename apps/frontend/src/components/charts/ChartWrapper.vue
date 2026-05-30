<script setup lang="ts">
// =============================================================================
// ChartWrapper — HOC around every ECharts instance (components.md §6.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Responsibilities:
//  - own the ECharts lifecycle via useChart (theme, resize, dispose)
//  - swap in ChartSkeleton (loading) / ChartEmpty (empty | error)
//  - dim the chart during background refetch (data-viz.md §6, stale-while-revalidate)
//  - relay click / legendselectchanged / datazoom as Vue events
//  - expose role="img" + aria-label and a "View as table" disclosure so
//    keyboard / screen-reader users can reach the underlying data (a11y §4.6)
// =============================================================================
import { ref, shallowRef, watch } from 'vue'
import type { EChartsOption } from '@/charts/echarts'
import { useChart } from '@/composables/useChart'
import ChartSkeleton from './ChartSkeleton.vue'
import ChartEmpty from './ChartEmpty.vue'

const props = withDefaults(
  defineProps<{
    option: EChartsOption
    title: string
    loading?: boolean
    error?: boolean
    empty?: boolean
    stale?: boolean
    height?: number
  }>(),
  { loading: false, error: false, empty: false, stale: false, height: 280 },
)

const emit = defineEmits<{
  click: [params: unknown]
  legendChange: [params: unknown]
  dataZoom: [params: unknown]
  retry: []
}>()

const el = ref<HTMLElement | null>(null)
const optionRef = shallowRef<EChartsOption>(props.option)
watch(
  () => props.option,
  (next) => {
    optionRef.value = next
  },
)

const { chart } = useChart(el, optionRef)

// Wire ECharts native events to Vue emits once the instance exists.
watch(chart, (instance) => {
  if (!instance) return
  instance.on('click', (params) => emit('click', params))
  instance.on('legendselectchanged', (params) => emit('legendChange', params))
  instance.on('datazoom', (params) => emit('dataZoom', params))
})
</script>

<template>
  <div class="sf-chart" :style="{ minHeight: `${height}px` }">
    <ChartSkeleton v-if="loading" :height="height" />
    <ChartEmpty
      v-else-if="error"
      variant="error"
      :height="height"
      @retry="emit('retry')"
    />
    <ChartEmpty v-else-if="empty" variant="no-data" :height="height" />
    <template v-else>
      <div
        ref="el"
        class="sf-chart__canvas w-full transition-opacity"
        :class="{ 'opacity-50': stale }"
        :style="{ height: `${height}px` }"
        role="img"
        tabindex="0"
        :aria-label="title"
      />
      <details v-if="$slots.table" class="sf-chart__table mt-2 text-sm">
        <summary class="cursor-pointer text-fg-secondary hover:text-fg-primary">
          {{ $t('charts.viewAsTable') }}
        </summary>
        <div class="mt-2">
          <slot name="table" />
        </div>
      </details>
    </template>
  </div>
</template>

<style scoped>
.sf-chart__canvas:focus-visible {
  outline: 2px solid #ffffff;
  outline-offset: 2px;
}
</style>
