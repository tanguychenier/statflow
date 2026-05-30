<script setup lang="ts">
// =============================================================================
// Divider — horizontal/vertical separator with optional label (components.md §18)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { Separator } from 'reka-ui'
import { cn } from '@/lib/utils'

withDefaults(
  defineProps<{ orientation?: 'horizontal' | 'vertical'; label?: string; class?: string }>(),
  { orientation: 'horizontal' },
)
</script>

<template>
  <div
    v-if="label && orientation === 'horizontal'"
    class="flex items-center gap-3 text-xs text-fg-muted"
  >
    <Separator class="h-px flex-1 bg-border" />
    <span>{{ label }}</span>
    <Separator class="h-px flex-1 bg-border" />
  </div>
  <Separator
    v-else
    :orientation="orientation"
    :class="
      cn(
        orientation === 'vertical' ? 'mx-1 h-5 w-px' : 'h-px w-full',
        'bg-border',
        $props.class,
      )
    "
  />
</template>
