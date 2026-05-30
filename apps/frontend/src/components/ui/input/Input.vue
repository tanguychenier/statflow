<script setup lang="ts">
// =============================================================================
// Input — text input (components.md §3.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// v-model via `modelValue`. Leading/trailing slots wrap the field; the error
// state drives `aria-invalid` so screen readers announce validation failures.
// =============================================================================
import { computed, useSlots } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    type?: string
    error?: boolean
    disabled?: boolean
    class?: string
  }>(),
  { type: 'text', error: false, disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const slots = useSlots()
const hasLeading = computed(() => Boolean(slots.leading))
const hasTrailing = computed(() => Boolean(slots.trailing))

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <div class="sf-input-wrap relative flex items-center">
    <span v-if="hasLeading" class="sf-input-wrap__leading absolute inset-y-0 left-3 flex items-center text-fg-muted">
      <slot name="leading" />
    </span>
    <input
      :type="type"
      :value="modelValue"
      :disabled="disabled"
      :aria-invalid="error || undefined"
      v-bind="$attrs"
      :class="
        cn(
          'h-9 w-full rounded-md border bg-bg-overlay px-3 text-sm text-fg-primary',
          'placeholder:text-fg-muted transition-colors outline-none',
          'hover:border-border-strong focus:ring-[1.5px] focus:ring-border-focus',
          'disabled:cursor-not-allowed disabled:opacity-40',
          error ? 'border-negative focus:ring-negative' : 'border-border',
          hasLeading && 'pl-9',
          hasTrailing && 'pr-9',
          props.class,
        )
      "
      @input="onInput"
    />
    <span v-if="hasTrailing" class="sf-input-wrap__trailing absolute inset-y-0 right-3 flex items-center text-fg-muted">
      <slot name="trailing" />
    </span>
  </div>
</template>
