<script setup lang="ts">
// =============================================================================
// SparkLine — 64px micro line for MetricCard (data-viz.md §3.8)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Display-only (no interaction, no axes). Uses useChart directly rather than
// ChartWrapper — there is no loading/empty UI for a decorative micro-chart.
// =============================================================================
import { ref, computed } from 'vue'
import { useChart } from '@/composables/useChart'
import { buildSparkLineOption } from '@/charts/options'
import type { TrendDirection } from '@/types'

const props = withDefaults(
  defineProps<{ data: number[]; direction?: TrendDirection; height?: number }>(),
  { direction: 'neutral', height: 64 },
)

const el = ref<HTMLElement | null>(null)
const option = computed(() => buildSparkLineOption(props.data, props.direction))
useChart(el, option)
</script>

<template>
  <div
    ref="el"
    class="sf-sparkline w-full"
    :style="{ height: `${height}px` }"
    aria-hidden="true"
  />
</template>
