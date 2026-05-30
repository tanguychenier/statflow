<script setup lang="ts">
// =============================================================================
// ConfirmDialog — destructive confirmation (components.md §10.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Built on Reka UI AlertDialog: focus is trapped and the confirm button is
// focused by default. For destructive operations Escape does not dismiss — the
// user must explicitly cancel or confirm (accessibility.md §3.3).
// =============================================================================
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from 'reka-ui'
import Button from '@/components/ui/button/Button.vue'
import type { ButtonVariant } from '@/components/ui/button'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
    confirmVariant?: ButtonVariant
    loading?: boolean
  }>(),
  { confirmLabel: 'Confirm', cancelLabel: 'Cancel', confirmVariant: 'destructive', loading: false },
)

const emit = defineEmits<{ 'update:open': [value: boolean]; confirm: []; cancel: [] }>()
</script>

<template>
  <AlertDialogRoot :open="open" @update:open="emit('update:open', $event)">
    <AlertDialogPortal>
      <AlertDialogOverlay class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" />
      <AlertDialogContent
        class="fixed left-1/2 top-1/2 z-50 w-[400px] max-w-[calc(100vw-48px)] -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-bg-overlay p-5 shadow-xl outline-none"
      >
        <AlertDialogTitle class="text-base font-semibold text-fg-primary">
          {{ title }}
        </AlertDialogTitle>
        <AlertDialogDescription v-if="description" class="mt-2 text-sm text-fg-secondary">
          {{ description }}
        </AlertDialogDescription>
        <div class="mt-5 flex justify-end gap-2">
          <AlertDialogCancel as-child>
            <Button variant="outline" size="sm" @click="emit('cancel')">{{ cancelLabel }}</Button>
          </AlertDialogCancel>
          <AlertDialogAction as-child>
            <Button :variant="confirmVariant" size="sm" :loading="loading" @click="emit('confirm')">
              {{ confirmLabel }}
            </Button>
          </AlertDialogAction>
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>
