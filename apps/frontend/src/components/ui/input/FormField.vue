<script setup lang="ts">
// =============================================================================
// FormField — label + control + error wiring (accessibility.md §4.4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Generates ids and the aria-describedby/aria-invalid relationships so screen
// readers announce the error against the right control. The control is provided
// via the default slot, which receives the generated `id` and describedby ids.
// =============================================================================
import { computed, useId } from 'vue'
import Label from './Label.vue'

const props = withDefaults(
  defineProps<{ label?: string; required?: boolean; error?: string; hint?: string }>(),
  { required: false },
)

const fieldId = useId()
const errorId = computed(() => `${fieldId}-error`)
const hintId = computed(() => `${fieldId}-hint`)

const describedBy = computed(() => {
  const ids: string[] = []
  if (props.hint) ids.push(hintId.value)
  if (props.error) ids.push(errorId.value)
  return ids.length ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="sf-field flex flex-col gap-1.5">
    <Label v-if="label" :for="fieldId" :required="required">{{ label }}</Label>
    <slot :id="fieldId" :described-by="describedBy" :invalid="Boolean(error)" />
    <p v-if="hint && !error" :id="hintId" class="text-xs text-fg-secondary">{{ hint }}</p>
    <p v-if="error" :id="errorId" role="alert" class="text-xs text-negative-text">{{ error }}</p>
  </div>
</template>
