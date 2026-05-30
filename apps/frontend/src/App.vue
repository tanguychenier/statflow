<script setup lang="ts">
// =============================================================================
// App.vue — Root application component
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Toaster } from '@/components/ui/toast'
import { CommandPalette } from '@/components/CommandPalette'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const { t, locale } = useI18n()
const { bootstrapping } = storeToRefs(useAuthStore())

const SITE_NAME = 'Statflow'

// Keep document.title in sync with both the active route and the current
// locale — switching language must re-render the tab title.
watch(
  [() => route.meta.titleKey, locale],
  ([titleKey]) => {
    document.title = titleKey ? `${t(titleKey as string)} — ${SITE_NAME}` : SITE_NAME
  },
  { immediate: true },
)
</script>

<template>
  <div
    v-if="bootstrapping"
    class="flex min-h-dvh items-center justify-center text-sm text-fg-secondary"
    role="status"
    aria-live="polite"
  >
    {{ t('common.loading') }}
  </div>
  <template v-else>
    <RouterView />
    <CommandPalette />
    <Toaster />
  </template>
</template>
