<script setup lang="ts">
// =============================================================================
// Chip — removable filter chip (components.md §14.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

withDefaults(defineProps<{ label: string; removable?: boolean; class?: string }>(), {
  removable: true,
})

const emit = defineEmits<{ remove: [] }>()
</script>

<template>
  <span
    :class="
      cn(
        'inline-flex items-center gap-1.5 rounded-full bg-bg-subtle px-2.5 py-1 text-xs text-fg-primary',
        $props.class,
      )
    "
  >
    <span class="truncate">{{ label }}</span>
    <button
      v-if="removable"
      type="button"
      class="-mr-0.5 flex size-4 items-center justify-center rounded-full text-fg-muted hover:bg-bg-muted hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus"
      :aria-label="`Remove ${label}`"
      @click="emit('remove')"
    >
      <X class="size-3" aria-hidden="true" />
    </button>
  </span>
</template>
