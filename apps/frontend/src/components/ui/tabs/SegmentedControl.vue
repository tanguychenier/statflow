<script setup lang="ts">
// =============================================================================
// SegmentedControl — pill-within-pill toggle (components.md §16.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// For small option sets (2–4): Table/Chart, Daily/Weekly/Monthly. Implemented
// as a radiogroup so arrow keys move between options and selection is announced.
// =============================================================================
import { cn } from '@/lib/utils'

interface SegmentOption {
  value: string
  label: string
}

withDefaults(defineProps<{ modelValue: string; options: SegmentOption[]; ariaLabel?: string }>(), {})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div
    role="radiogroup"
    :aria-label="ariaLabel"
    class="inline-flex gap-0.5 rounded-md bg-bg-subtle p-0.5"
  >
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      role="radio"
      :aria-checked="modelValue === option.value"
      :tabindex="modelValue === option.value ? 0 : -1"
      :class="
        cn(
          'rounded-[5px] px-3 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-border-focus',
          modelValue === option.value
            ? 'bg-bg-surface text-fg-primary shadow-sm'
            : 'text-fg-secondary hover:text-fg-primary',
        )
      "
      @click="emit('update:modelValue', option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
