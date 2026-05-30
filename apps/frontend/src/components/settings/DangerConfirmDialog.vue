<script setup lang="ts">
// =============================================================================
// DangerConfirmDialog — type-to-confirm destructive action (screens.md §6.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Built on Reka UI AlertDialog so focus is trapped and Escape does NOT dismiss
// (accessibility.md §3.3) — the user must cancel or confirm explicitly. The
// confirm button stays disabled until the typed site name matches exactly.
// =============================================================================
import { ref, watch } from 'vue'
import {
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from 'reka-ui'
import { useI18n } from 'vue-i18n'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Label from '@/components/ui/input/Label.vue'
import { isDangerConfirmMatch } from './settingsModel'
import type { ButtonVariant } from '@/components/ui/button'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    siteName: string
    confirmLabel: string
    confirmVariant?: ButtonVariant
    loading?: boolean
  }>(),
  { confirmVariant: 'destructive', loading: false },
)

const emit = defineEmits<{ 'update:open': [value: boolean]; confirm: []; cancel: [] }>()

const { t } = useI18n()

const typed = ref('')
const matches = ref(false)

// Reset the confirmation field every time the dialog opens.
watch(
  () => props.open,
  (open) => {
    if (open) {
      typed.value = ''
      matches.value = false
    }
  },
)

watch(typed, (value) => {
  matches.value = isDangerConfirmMatch(props.siteName, value)
})

function onConfirm() {
  if (matches.value) emit('confirm')
}
</script>

<template>
  <AlertDialogRoot :open="open" @update:open="emit('update:open', $event)">
    <AlertDialogPortal>
      <AlertDialogOverlay class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" />
      <AlertDialogContent
        class="fixed left-1/2 top-1/2 z-50 w-[440px] max-w-[calc(100vw-48px)] -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-bg-overlay p-5 shadow-xl outline-none"
      >
        <AlertDialogTitle class="text-base font-semibold text-fg-primary">
          {{ title }}
        </AlertDialogTitle>
        <AlertDialogDescription v-if="description" class="mt-2 text-sm text-fg-secondary">
          {{ description }}
        </AlertDialogDescription>

        <div class="mt-4 flex flex-col gap-1.5">
          <Label for="danger-confirm-input">
            {{ t('settings.danger.confirmName', { name: siteName }) }}
          </Label>
          <Input
            id="danger-confirm-input"
            v-model="typed"
            autocomplete="off"
            :placeholder="siteName"
            :error="typed.length > 0 && !matches"
          />
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <AlertDialogCancel as-child>
            <Button variant="outline" size="sm" @click="emit('cancel')">
              {{ t('common.cancel') }}
            </Button>
          </AlertDialogCancel>
          <Button
            :variant="confirmVariant"
            size="sm"
            :loading="loading"
            :disabled="!matches"
            @click="onConfirm"
          >
            {{ confirmLabel }}
          </Button>
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>
