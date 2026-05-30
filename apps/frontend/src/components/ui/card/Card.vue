<script setup lang="ts">
// =============================================================================
// Card — base surface card (components.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <section
    :class="
      cn(
        'rounded-xl border border-border bg-bg-surface px-6 py-5 shadow-sm',
        $props.class,
      )
    "
  >
    <slot />
  </section>
</template>
