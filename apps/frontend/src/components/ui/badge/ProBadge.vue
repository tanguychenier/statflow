<script setup lang="ts">
// =============================================================================
// ProBadge — gradient "Pro" pill for locked features (components.md §14.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <span
    :class="
      cn(
        'inline-flex items-center rounded-full px-2 py-0.5 text-[0.625rem] font-semibold uppercase tracking-widest text-white',
        'bg-gradient-to-r from-indigo-500 to-violet-500',
        $props.class,
      )
    "
    style="background-image: linear-gradient(to right, var(--sf-indigo-500), var(--sf-violet-500))"
  >
    Pro
  </span>
</template>
