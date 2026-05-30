<script setup lang="ts">
// =============================================================================
// SkeletonCard — MetricCard-shaped placeholder (components.md §12)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import Skeleton from './Skeleton.vue'
</script>

<template>
  <div
    class="flex flex-col gap-2 rounded-xl border border-border bg-bg-surface px-6 py-5 shadow-sm"
    aria-busy="true"
  >
    <Skeleton class="h-3.5 w-3/5" />
    <Skeleton class="h-9 w-4/5" />
    <Skeleton class="h-5 w-2/5" />
    <Skeleton class="mt-2 h-16 w-full" />
    <span class="sr-only">{{ $t('common.loading') }}</span>
  </div>
</template>
