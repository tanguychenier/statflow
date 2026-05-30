<script setup lang="ts">
// =============================================================================
// Skeleton — shimmer placeholder block (components.md §12)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The shimmer is suppressed under prefers-reduced-motion (accessibility.md §5):
// a static muted block is shown instead. Always aria-hidden — the surrounding
// container carries the aria-busy/live state.
// =============================================================================
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <div :class="cn('sf-skeleton rounded-sm', $props.class)" aria-hidden="true" />
</template>

<style scoped>
.sf-skeleton {
  background: linear-gradient(
    90deg,
    var(--sf-bg-subtle) 25%,
    var(--sf-bg-muted) 50%,
    var(--sf-bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: sf-shimmer 1.5s linear infinite;
}

@keyframes sf-shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sf-skeleton {
    animation: none;
    background: var(--sf-bg-subtle);
  }
}
</style>
