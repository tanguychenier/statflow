<script setup lang="ts">
// =============================================================================
// Modal — dialog built on Reka UI DialogRoot (components.md §10)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Open state is v-model'd. Focus is trapped by Reka and restored to the trigger
// on close; Escape closes non-destructive modals (alert dialogs use AlertDialog
// instead). Five widths map to the spec sizes.
// =============================================================================
import { computed } from 'vue'
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'
import { X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    description?: string
    size?: ModalSize
    closeLabel?: string
  }>(),
  { size: 'md', closeLabel: 'Close' },
)

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const SIZE_CLASS: Record<ModalSize, string> = {
  sm: 'w-[400px]',
  md: 'w-[560px]',
  lg: 'w-[720px]',
  xl: 'w-[960px]',
  full: 'w-[calc(100vw-48px)] h-[calc(100vh-48px)]',
}

const contentClass = computed(() =>
  cn(
    'fixed left-1/2 top-1/2 z-50 flex max-h-[calc(100vh-48px)] max-w-[calc(100vw-48px)] -translate-x-1/2 -translate-y-1/2 flex-col',
    'rounded-xl border border-border bg-bg-overlay shadow-xl outline-none',
    SIZE_CLASS[props.size],
  ),
)
</script>

<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" />
      <DialogContent :class="contentClass">
        <header class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
          <div class="min-w-0">
            <DialogTitle v-if="title" class="text-base font-semibold text-fg-primary">
              {{ title }}
            </DialogTitle>
            <DialogDescription v-if="description" class="mt-0.5 text-sm text-fg-secondary">
              {{ description }}
            </DialogDescription>
            <slot name="header" />
          </div>
          <DialogClose
            class="flex size-8 shrink-0 items-center justify-center rounded-md text-fg-secondary hover:bg-bg-subtle hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus"
            :aria-label="closeLabel"
          >
            <X class="size-4" aria-hidden="true" />
          </DialogClose>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
          <slot />
        </div>

        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-border px-5 py-4">
          <slot name="footer" />
        </footer>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
