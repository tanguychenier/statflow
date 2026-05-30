<script setup lang="ts">
// =============================================================================
// CardHeader — title row with optional trailing action (components.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { cn } from '@/lib/utils'

defineProps<{ title?: string; class?: string }>()
</script>

<template>
  <header :class="cn('mb-4 flex items-start justify-between gap-3', $props.class)">
    <div class="min-w-0">
      <slot name="title">
        <h2 v-if="title" class="truncate text-sm font-semibold text-fg-primary">{{ title }}</h2>
      </slot>
      <slot name="subtitle" />
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 items-center gap-1">
      <slot name="actions" />
    </div>
  </header>
</template>
