<script setup lang="ts">
// =============================================================================
// RealtimeTrendChart — active-users 30-minute bar trend (screens.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Animated vertical bar chart of the last 30 one-minute buckets. The rightmost
// (live) bar is accent-tinted by the option builder and the canvas gently
// pulses to signal liveness; both animations collapse under reduced motion.
// =============================================================================
import { computed } from 'vue'
import ChartWrapper from '@/components/charts/ChartWrapper.vue'
import { useChartFormat } from '@/composables/useChartFormat'
import { useReducedMotion } from '@/composables/useReducedMotion'
import { buildRealtimeTrendOption } from './realtimeChart'
import type { TrendBucket } from './realtimeModel'

const props = withDefaults(
  defineProps<{
    title: string
    buckets: TrendBucket[]
    loading?: boolean
    error?: boolean
    height?: number
  }>(),
  { loading: false, error: false, height: 240 },
)

const emit = defineEmits<{ retry: [] }>()

const { formatters } = useChartFormat()
const { prefersReduced } = useReducedMotion()

const isEmpty = computed(() => props.buckets.every((bucket) => bucket.count === 0))
const option = computed(() =>
  buildRealtimeTrendOption(props.buckets, formatters.value, { animate: !prefersReduced.value }),
)
</script>

<template>
  <div class="trend" :class="{ 'trend--pulse': !prefersReduced && !loading && !error }">
    <ChartWrapper
      :title="title"
      :option="option"
      :loading="loading"
      :error="error"
      :empty="isEmpty"
      :height="height"
      @retry="emit('retry')"
    />
  </div>
</template>

<style scoped>
.trend {
  border-radius: var(--sf-radius-lg);
}

.trend--pulse :deep(.sf-chart__canvas) {
  animation: trend-breathe 2.4s var(--sf-ease-in-out) infinite;
}

@keyframes trend-breathe {
  0%,
  100% {
    filter: drop-shadow(0 0 0 transparent);
  }
  50% {
    filter: drop-shadow(0 0 6px var(--sf-accent-subtle));
  }
}

@media (prefers-reduced-motion: reduce) {
  .trend--pulse :deep(.sf-chart__canvas) {
    animation: none;
  }
}
</style>
