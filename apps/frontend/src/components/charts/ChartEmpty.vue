<script setup lang="ts">
// =============================================================================
// ChartEmpty — empty / error state for charts (data-viz.md §6)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import EmptyState, { type EmptyStateVariant } from '@/components/ui/empty-state/EmptyState.vue'

const props = withDefaults(
  defineProps<{
    variant?: 'no-data' | 'error'
    height?: number
    title?: string
    description?: string
    actionLabel?: string
  }>(),
  { variant: 'no-data', height: 280 },
)

const emit = defineEmits<{ retry: [] }>()

const mappedVariant = computed<EmptyStateVariant>(() =>
  props.variant === 'error' ? 'error' : 'no-data',
)
</script>

<template>
  <div class="flex w-full items-center justify-center" :style="{ minHeight: `${height}px` }">
    <EmptyState
      :variant="mappedVariant"
      :title="title ?? $t('common.noData')"
      :description="description"
      :action-label="variant === 'error' ? (actionLabel ?? $t('common.retry')) : actionLabel"
      @action="emit('retry')"
    />
  </div>
</template>
