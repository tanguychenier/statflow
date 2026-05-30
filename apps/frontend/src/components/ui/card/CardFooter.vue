<script setup lang="ts">
// =============================================================================
// CardFooter — footnote / actions row (components.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <footer
    :class="cn('mt-4 flex items-center justify-between gap-3 text-sm text-fg-secondary', $props.class)"
  >
    <slot />
  </footer>
</template>
