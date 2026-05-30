<script setup lang="ts">
// =============================================================================
// EmptyState — zero-data / error placeholder (components.md §13)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Minimal line-art illustrations drawn with `currentColor` so they adapt to
// both themes without separate assets. Five variants map to the spec; the
// optional action renders a Button via slot or the `action` prop callback.
// =============================================================================
import { computed } from 'vue'
import Button from '@/components/ui/button/Button.vue'

export type EmptyStateVariant = 'no-data' | 'no-results' | 'no-setup' | 'no-access' | 'error'

const props = withDefaults(
  defineProps<{
    variant?: EmptyStateVariant
    title: string
    description?: string
    actionLabel?: string
  }>(),
  { variant: 'no-data' },
)

const emit = defineEmits<{ action: [] }>()

const ICONS: Record<EmptyStateVariant, string> = {
  'no-data':
    'M3 3v18h18 M7 14l4-4 3 3 5-6',
  'no-results':
    'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z M21 21l-4.3-4.3',
  'no-setup':
    'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z',
  'no-access':
    'M6 11V8a6 6 0 0 1 12 0v3 M5 11h14v9H5z',
  error:
    'M12 9v4 M12 17h.01 M10.3 3.9l-8 13.8A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.3l-8-13.8a2 2 0 0 0-3.4 0z',
}

const iconPath = computed(() => ICONS[props.variant])
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center" role="status">
    <svg
      class="size-12 text-fg-muted"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.5"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path :d="iconPath" />
    </svg>
    <p class="text-base font-semibold text-fg-primary">{{ title }}</p>
    <p v-if="description" class="max-w-sm text-sm text-fg-secondary">{{ description }}</p>
    <slot name="action">
      <Button
        v-if="actionLabel"
        :variant="variant === 'error' ? 'outline' : 'default'"
        size="sm"
        class="mt-1"
        @click="emit('action')"
      >
        {{ actionLabel }}
      </Button>
    </slot>
  </div>
</template>
