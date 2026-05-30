<script setup lang="ts">
// =============================================================================
// Spinner — indeterminate loading indicator
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { Loader2 } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

withDefaults(defineProps<{ label?: string; class?: string }>(), { label: 'Loading' })
</script>

<template>
  <span role="status" :aria-label="label" :class="cn('inline-flex text-fg-secondary', $props.class)">
    <Loader2 class="size-4 animate-spin" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </span>
</template>
