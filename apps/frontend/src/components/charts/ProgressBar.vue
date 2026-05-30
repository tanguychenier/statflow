<script setup lang="ts">
// =============================================================================
// ProgressBar — CSS inline bar for top-N lists (data-viz.md §3.9)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'

const props = withDefaults(defineProps<{ value: number; max?: number; label?: string }>(), {
  max: 100,
})

const pct = computed(() => {
  if (props.max <= 0) return 0
  return Math.min(100, Math.max(0, (props.value / props.max) * 100))
})
</script>

<template>
  <div
    class="sf-progress h-1 w-full overflow-hidden rounded-full bg-accent-subtle"
    role="progressbar"
    :aria-valuenow="Math.round(pct)"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="label"
  >
    <div class="h-full rounded-full bg-accent transition-[width]" :style="{ width: `${pct}%` }" />
  </div>
</template>
