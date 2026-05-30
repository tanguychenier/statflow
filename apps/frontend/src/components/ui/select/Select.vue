<script setup lang="ts">
// =============================================================================
// Select — single-select dropdown on Reka UI Select (components.md §3.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Generic over the option value type. Trigger matches the text-input sizing;
// the listbox uses the overlay surface and shows a checkmark on the active row.
// =============================================================================
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'
import { Check, ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

interface SelectOption {
  value: string
  label: string
  disabled?: boolean
}

withDefaults(
  defineProps<{
    modelValue?: string
    options: SelectOption[]
    placeholder?: string
    disabled?: boolean
    class?: string
  }>(),
  { placeholder: 'Select…', disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <SelectRoot
    :model-value="modelValue"
    :disabled="disabled"
    @update:model-value="emit('update:modelValue', $event as string)"
  >
    <SelectTrigger
      :class="
        cn(
          'inline-flex h-9 items-center justify-between gap-2 rounded-md border border-border bg-bg-overlay px-3 text-sm text-fg-primary',
          'hover:border-border-strong focus:ring-[1.5px] focus:ring-border-focus outline-none',
          'disabled:cursor-not-allowed disabled:opacity-40',
          $props.class,
        )
      "
    >
      <SelectValue :placeholder="placeholder" />
      <ChevronDown class="size-4 text-fg-muted" aria-hidden="true" />
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        position="popper"
        :side-offset="6"
        class="z-50 max-h-72 min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-md border border-border bg-bg-overlay shadow-lg"
      >
        <SelectViewport class="p-1">
          <SelectItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :disabled="option.disabled"
            class="relative flex h-8 cursor-pointer select-none items-center rounded-sm px-2 pr-7 text-sm text-fg-primary outline-none data-[highlighted]:bg-bg-subtle data-[disabled]:opacity-40"
          >
            <SelectItemText>{{ option.label }}</SelectItemText>
            <SelectItemIndicator class="absolute right-2 inline-flex items-center">
              <Check class="size-4 text-accent-text" aria-hidden="true" />
            </SelectItemIndicator>
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
