<script setup lang="ts">
// =============================================================================
// Label — form field label with required marker (accessibility.md §4.4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { Label as RekaLabel } from 'reka-ui'
import { cn } from '@/lib/utils'

withDefaults(defineProps<{ for?: string; required?: boolean; class?: string }>(), {
  required: false,
})
</script>

<template>
  <RekaLabel
    :for="$props.for"
    :class="cn('flex items-center gap-1 text-sm font-medium text-fg-primary', $props.class)"
  >
    <slot />
    <template v-if="required">
      <span aria-hidden="true" class="text-negative">*</span>
      <span class="sr-only">(required)</span>
    </template>
  </RekaLabel>
</template>
