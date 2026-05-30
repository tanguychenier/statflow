<script setup lang="ts">
// =============================================================================
// Switch — pill toggle on Reka UI Switch (components.md §3.7)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { SwitchRoot, SwitchThumb } from 'reka-ui'
import { cn } from '@/lib/utils'

withDefaults(
  defineProps<{ modelValue?: boolean; disabled?: boolean; ariaLabel?: string; class?: string }>(),
  { disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
  <SwitchRoot
    :model-value="modelValue"
    :disabled="disabled"
    :aria-label="ariaLabel"
    :class="
      cn(
        'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors outline-none',
        'focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2',
        'data-[state=checked]:bg-accent data-[state=unchecked]:bg-bg-muted',
        'disabled:cursor-not-allowed disabled:opacity-40',
        $props.class,
      )
    "
    @update:model-value="emit('update:modelValue', $event)"
  >
    <SwitchThumb
      class="block size-4 translate-x-0.5 rounded-full bg-white shadow-sm transition-transform data-[state=checked]:translate-x-[18px]"
    />
  </SwitchRoot>
</template>
