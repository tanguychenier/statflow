<script setup lang="ts">
// =============================================================================
// Checkbox — 16px checkbox on Reka UI (components.md §3.5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Supports the indeterminate state via the `'indeterminate'` model value, used
// by the data table header to select all/some rows.
// =============================================================================
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { Check, Minus } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

type CheckedState = boolean | 'indeterminate'

withDefaults(
  defineProps<{ modelValue?: CheckedState; disabled?: boolean; ariaLabel?: string; class?: string }>(),
  { disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: CheckedState] }>()
</script>

<template>
  <CheckboxRoot
    :model-value="modelValue"
    :disabled="disabled"
    :aria-label="ariaLabel"
    :class="
      cn(
        'flex size-4 shrink-0 items-center justify-center rounded-[4px] border transition-colors outline-none',
        'focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2',
        'data-[state=checked]:border-accent data-[state=checked]:bg-accent',
        'data-[state=indeterminate]:border-accent data-[state=indeterminate]:bg-accent',
        'data-[state=unchecked]:border-border-strong',
        'disabled:cursor-not-allowed disabled:opacity-40',
        $props.class,
      )
    "
    @update:model-value="emit('update:modelValue', $event)"
  >
    <CheckboxIndicator class="flex items-center justify-center text-white">
      <Minus v-if="modelValue === 'indeterminate'" class="size-3" aria-hidden="true" />
      <Check v-else class="size-3" aria-hidden="true" />
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
