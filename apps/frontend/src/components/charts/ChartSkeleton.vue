<script setup lang="ts">
// =============================================================================
// ChartSkeleton — chart loading placeholder (data-viz.md §6)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'

withDefaults(defineProps<{ height?: number }>(), { height: 280 })
</script>

<template>
  <div
    class="flex w-full flex-col justify-end gap-2"
    :style="{ height: `${height}px` }"
    aria-busy="true"
    aria-live="polite"
  >
    <span class="sr-only">{{ $t('common.loading') }}</span>
    <Skeleton class="h-full w-full" />
  </div>
</template>
