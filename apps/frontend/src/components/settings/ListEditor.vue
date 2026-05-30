<script setup lang="ts">
// =============================================================================
// ListEditor — add/remove a list of short string values as chips (screens.md §6.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Drives the excluded-IPs and allowed-domains editors. Entries are added from a
// single input (comma/space/newline separated, parsed via parseList) and shown
// as removable chips. Per-item validity is supplied by the parent's `validate`
// predicate so invalid chips render with an error treatment and an alert.
// =============================================================================
import { computed, ref } from 'vue'
import { X } from 'lucide-vue-next'
import Input from '@/components/ui/input/Input.vue'
import Button from '@/components/ui/button/Button.vue'
import { cn } from '@/lib/utils'
import { parseList } from './settingsModel'

const props = withDefaults(
  defineProps<{
    modelValue: string[]
    validate?: (value: string) => boolean
    placeholder?: string
    addLabel?: string
    removeLabel?: string
    emptyLabel?: string
    invalidLabel?: string
    disabled?: boolean
    /** Label used for the input's accessible name (the visible label is external). */
    inputAriaLabel?: string
  }>(),
  {
    validate: () => true,
    addLabel: 'Add',
    removeLabel: 'Remove',
    emptyLabel: '',
    invalidLabel: '',
    disabled: false,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const entry = ref('')

const invalidEntries = computed(() => props.modelValue.filter((item) => !props.validate(item)))
const hasInvalid = computed(() => invalidEntries.value.length > 0)

function isInvalid(value: string): boolean {
  return !props.validate(value)
}

function commit() {
  const additions = parseList(entry.value)
  if (additions.length === 0) return
  const seen = new Set(props.modelValue)
  const next = [...props.modelValue]
  for (const value of additions) {
    if (!seen.has(value)) {
      seen.add(value)
      next.push(value)
    }
  }
  emit('update:modelValue', next)
  entry.value = ''
}

function onEnter(event: KeyboardEvent) {
  event.preventDefault()
  commit()
}

function remove(value: string) {
  emit(
    'update:modelValue',
    props.modelValue.filter((item) => item !== value),
  )
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center gap-2">
      <Input
        v-model="entry"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-label="inputAriaLabel"
        @keydown.enter="onEnter"
      />
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="disabled || entry.trim().length === 0"
        @click="commit"
      >
        {{ addLabel }}
      </Button>
    </div>

    <p v-if="modelValue.length === 0 && emptyLabel" class="text-xs text-fg-muted">
      {{ emptyLabel }}
    </p>

    <ul v-else class="flex flex-wrap gap-1.5">
      <li v-for="value in modelValue" :key="value">
        <span
          :class="
            cn(
              'inline-flex items-center gap-1 rounded-md border px-2 py-1 font-mono text-xs',
              isInvalid(value)
                ? 'border-negative bg-negative-bg text-negative-text'
                : 'border-border bg-bg-subtle text-fg-secondary',
            )
          "
        >
          {{ value }}
          <button
            type="button"
            class="flex size-4 items-center justify-center rounded hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus outline-none disabled:opacity-40"
            :disabled="disabled"
            :aria-label="`${removeLabel} ${value}`"
            @click="remove(value)"
          >
            <X class="size-3" aria-hidden="true" />
          </button>
        </span>
      </li>
    </ul>

    <p v-if="hasInvalid && invalidLabel" role="alert" class="text-xs text-negative-text">
      {{ invalidLabel }}
    </p>
  </div>
</template>
