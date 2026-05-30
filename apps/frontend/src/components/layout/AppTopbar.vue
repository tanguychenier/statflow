<script setup lang="ts">
// =============================================================================
// AppTopbar — global top bar (components.md §9.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Sidebar toggle · site selector · global date range · ⌘K · notifications ·
// avatar/theme. The site selector and notifications are intentionally thin
// shells here; the settings/notification screen agents flesh out their menus.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Bell, Menu, Moon, Search, Sun } from 'lucide-vue-next'
import { useTheme } from '@/composables/useTheme'
import { useCommandPalette } from '@/composables/useCommandPalette'
import { useCurrentSiteStore } from '@/stores/currentSite'
import Select from '@/components/ui/select/Select.vue'
import Tooltip from '@/components/ui/tooltip/Tooltip.vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import { DateRangePicker } from '@/components/DateRangePicker'

const emit = defineEmits<{ 'toggle-sidebar': [] }>()

const { t } = useI18n()
const { isDark, toggleTheme } = useTheme()
const { open: openPalette } = useCommandPalette()
const siteStore = useCurrentSiteStore()
const { sites, currentSiteId } = storeToRefs(siteStore)

const siteOptions = computed(() => sites.value.map((site) => ({ value: site.id, label: site.name })))
</script>

<template>
  <header class="topbar" role="banner">
    <div class="topbar__start">
      <button
        class="topbar__icon-btn"
        type="button"
        aria-label="Toggle sidebar"
        @click="emit('toggle-sidebar')"
      >
        <Menu class="size-4" aria-hidden="true" />
      </button>
      <Select
        v-if="siteOptions.length > 0"
        :model-value="currentSiteId ?? undefined"
        :options="siteOptions"
        class="min-w-44"
        @update:model-value="siteStore.selectSite($event)"
      />
    </div>

    <div class="topbar__end">
      <DateRangePicker />

      <Tooltip :content="t('common.search')">
        <button class="topbar__icon-btn" type="button" :aria-label="t('common.search')" @click="openPalette">
          <Search class="size-4" aria-hidden="true" />
        </button>
      </Tooltip>

      <button class="topbar__icon-btn" type="button" aria-label="Notifications">
        <Bell class="size-4" aria-hidden="true" />
      </button>

      <button
        class="topbar__icon-btn"
        type="button"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        @click="toggleTheme"
      >
        <Sun v-if="isDark" class="size-4" aria-hidden="true" />
        <Moon v-else class="size-4" aria-hidden="true" />
      </button>

      <Avatar size="sm" :name="siteStore.currentSite?.name" />
    </div>
  </header>
</template>

<style scoped>
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--sf-topbar-height);
  padding-inline: var(--sf-space-4);
  background-color: var(--sf-bg-surface);
  border-block-end: 1px solid var(--sf-border);
  position: sticky;
  inset-block-start: 0;
  z-index: 10;
  flex-shrink: 0;
  gap: var(--sf-space-3);
}

.topbar__start,
.topbar__end {
  display: flex;
  align-items: center;
  gap: var(--sf-space-2);
  min-width: 0;
}

.topbar__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: var(--sf-fg-secondary);
  border-radius: var(--sf-radius-md);
  cursor: pointer;
  transition:
    background-color var(--sf-duration-base) var(--sf-ease-default),
    color var(--sf-duration-base) var(--sf-ease-default);
}

.topbar__icon-btn:hover {
  background-color: var(--sf-bg-subtle);
  color: var(--sf-fg-primary);
}
</style>
