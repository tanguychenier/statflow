<script setup lang="ts">
// =============================================================================
// DangerZone — reset data / delete site (screens.md §6.2 Danger Zone)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Two irreversible actions, each gated behind a type-to-confirm dialog. "Reset
// data" requires manage permission; "Delete site" requires ownership (ADR-0009),
// so its row is hidden when the viewer cannot delete. Mutations run in the view.
// =============================================================================
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import Button from '@/components/ui/button/Button.vue'
import { Divider } from '@/components/ui/divider'
import DangerConfirmDialog from './DangerConfirmDialog.vue'
import type { Site } from '@/api/types'

type DangerAction = 'reset' | 'delete'

withDefaults(
  defineProps<{
    site: Site
    canReset?: boolean
    canDelete?: boolean
    resetting?: boolean
    deleting?: boolean
  }>(),
  { canReset: false, canDelete: false, resetting: false, deleting: false },
)

const emit = defineEmits<{ reset: []; delete: [] }>()

const { t } = useI18n()

const activeDialog = ref<DangerAction | null>(null)

function open(action: DangerAction) {
  activeDialog.value = action
}

function close() {
  activeDialog.value = null
}

function confirm() {
  if (activeDialog.value === 'reset') emit('reset')
  else if (activeDialog.value === 'delete') emit('delete')
  close()
}
</script>

<template>
  <Card class="border-negative/40">
    <h2 class="text-sm font-semibold text-negative-text">{{ t('settings.sections.dangerZone') }}</h2>

    <div v-if="canReset" class="mt-4 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm font-medium text-fg-primary">{{ t('settings.danger.resetData') }}</p>
        <p class="text-xs text-fg-secondary">{{ t('settings.danger.resetDataDesc') }}</p>
      </div>
      <Button variant="destructive" size="sm" :loading="resetting" @click="open('reset')">
        {{ t('settings.danger.resetData') }}
      </Button>
    </div>

    <Divider v-if="canReset && canDelete" class="my-4" />

    <div v-if="canDelete" class="flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm font-medium text-fg-primary">{{ t('settings.danger.deleteSite') }}</p>
        <p class="text-xs text-fg-secondary">{{ t('settings.danger.deleteSiteDesc') }}</p>
      </div>
      <Button variant="destructive" size="sm" :loading="deleting" @click="open('delete')">
        {{ t('settings.danger.deleteSite') }}
      </Button>
    </div>

    <DangerConfirmDialog
      :open="activeDialog === 'reset'"
      :title="t('settings.danger.resetData')"
      :description="t('settings.danger.resetDataDesc')"
      :site-name="site.name"
      :confirm-label="t('settings.danger.resetData')"
      :loading="resetting"
      @update:open="(v) => !v && close()"
      @confirm="confirm"
      @cancel="close"
    />

    <DangerConfirmDialog
      :open="activeDialog === 'delete'"
      :title="t('settings.danger.deleteSite')"
      :description="t('settings.danger.deleteSiteDesc')"
      :site-name="site.name"
      :confirm-label="t('settings.danger.deleteSite')"
      :loading="deleting"
      @update:open="(v) => !v && close()"
      @confirm="confirm"
      @cancel="close"
    />
  </Card>
</template>
