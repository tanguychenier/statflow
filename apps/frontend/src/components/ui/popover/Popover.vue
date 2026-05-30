<script setup lang="ts">
// =============================================================================
// Popover — Reka UI Popover wrapper (components.md §15.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Floating panel without an arrow. `trigger` slot holds the anchor; default
// slot holds the panel body. Open state is optionally controllable via v-model.
// =============================================================================
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import { cn } from '@/lib/utils'

withDefaults(
  defineProps<{
    open?: boolean
    align?: 'start' | 'center' | 'end'
    side?: 'top' | 'right' | 'bottom' | 'left'
    class?: string
  }>(),
  { align: 'start', side: 'bottom' },
)

const emit = defineEmits<{ 'update:open': [value: boolean] }>()
</script>

<template>
  <PopoverRoot :open="open" @update:open="emit('update:open', $event)">
    <PopoverTrigger as-child>
      <slot name="trigger" />
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        :align="align"
        :side="side"
        :side-offset="6"
        :class="
          cn(
            'z-50 rounded-lg border border-border bg-bg-overlay p-2 shadow-lg outline-none',
            $props.class,
          )
        "
      >
        <slot />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
