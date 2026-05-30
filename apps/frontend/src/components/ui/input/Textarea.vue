<script setup lang="ts">
// =============================================================================
// Textarea — multi-line text input (components.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{ modelValue?: string; error?: boolean; disabled?: boolean; class?: string }>(),
  { error: false, disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <textarea
    :value="modelValue"
    :disabled="disabled"
    :aria-invalid="error || undefined"
    v-bind="$attrs"
    :class="
      cn(
        'min-h-20 w-full resize-y rounded-md border bg-bg-overlay px-3 py-2 text-sm text-fg-primary',
        'placeholder:text-fg-muted transition-colors outline-none',
        'hover:border-border-strong focus:ring-[1.5px] focus:ring-border-focus',
        'disabled:cursor-not-allowed disabled:opacity-40',
        error ? 'border-negative focus:ring-negative' : 'border-border',
        props.class,
      )
    "
    @input="onInput"
  />
</template>
