<script setup lang="ts">
// =============================================================================
// SkeletonText — N lines of text-shaped placeholders (components.md §12)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import Skeleton from './Skeleton.vue'

const props = withDefaults(defineProps<{ lines?: number }>(), { lines: 3 })

const lineCount = computed(() => Math.max(1, props.lines))
</script>

<template>
  <div class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton
      v-for="line in lineCount"
      :key="line"
      class="h-3.5"
      :class="line === lineCount && lineCount > 1 ? 'w-3/5' : 'w-full'"
    />
  </div>
</template>
