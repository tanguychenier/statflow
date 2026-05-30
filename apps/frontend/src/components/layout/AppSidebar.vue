<script setup lang="ts">
// =============================================================================
// AppSidebar — main navigation sidebar (components.md §9.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Collapsed (56px, icon-only + tooltip) / expanded (220px) per the spec. Every
// route here matches the router's named routes. Items the screen agents have
// not built yet (Click maps, Session recordings, Retention, Journeys) point at
// their future named routes; until those exist they render as disabled stubs so
// the IA is visible without dead links.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import {
  FileBarChart,
  Filter,
  Flame,
  GitMerge,
  LayoutDashboard,
  MousePointer,
  Radio,
  RotateCcw,
  Settings2,
  Video,
  type LucideIcon,
} from 'lucide-vue-next'
import Tooltip from '@/components/ui/tooltip/Tooltip.vue'
import ProBadge from '@/components/ui/badge/ProBadge.vue'

defineProps<{ collapsed: boolean }>()

const { t } = useI18n()
const route = useRoute()

interface NavItem {
  label: string
  to?: string
  icon: LucideIcon
  pro?: boolean
  group?: boolean
}

const sections = computed<NavItem[][]>(() => [
  [
    { label: t('nav.overview'), to: '/', icon: LayoutDashboard },
    { label: t('nav.realtime'), to: '/realtime', icon: Radio },
  ],
  [
    { label: t('nav.heatmaps'), to: '/behaviour/heatmaps', icon: Flame },
    { label: t('nav.clickMaps'), icon: MousePointer },
    { label: t('nav.sessionRecordings'), icon: Video, pro: true },
    { label: t('nav.pagesAndSources'), to: '/pages-sources', icon: FileBarChart },
    { label: t('nav.funnels'), to: '/funnels', icon: Filter },
    { label: t('nav.retention'), icon: RotateCcw },
    { label: t('nav.journeys'), icon: GitMerge },
  ],
  [{ label: t('nav.settings'), to: '/settings', icon: Settings2 }],
])

function isActive(to?: string): boolean {
  if (!to) return false
  if (to === '/') return route.path === '/'
  return route.path.startsWith(to)
}
</script>

<template>
  <nav class="sidebar" :class="{ 'sidebar--collapsed': collapsed }" aria-label="Main">
    <div class="sidebar__brand">
      <span class="sidebar__logo" aria-hidden="true">◆</span>
      <span v-if="!collapsed" class="sidebar__wordmark">Statflow</span>
    </div>

    <div class="sidebar__sections">
      <ul
        v-for="(section, sectionIndex) in sections"
        :key="sectionIndex"
        class="sidebar__nav"
        role="list"
      >
        <li v-for="item in section" :key="item.label">
          <Tooltip v-if="collapsed" :content="item.label" side="right">
            <component
              :is="item.to ? 'RouterLink' : 'span'"
              :to="item.to"
              class="sidebar__link"
              :class="{
                'sidebar__link--active': isActive(item.to),
                'sidebar__link--disabled': !item.to,
              }"
              :aria-current="isActive(item.to) ? 'page' : undefined"
              :aria-disabled="!item.to || undefined"
            >
              <component :is="item.icon" class="sidebar__icon" aria-hidden="true" />
            </component>
          </Tooltip>
          <component
            :is="item.to ? 'RouterLink' : 'span'"
            v-else
            :to="item.to"
            class="sidebar__link"
            :class="{
              'sidebar__link--active': isActive(item.to),
              'sidebar__link--disabled': !item.to,
            }"
            :aria-current="isActive(item.to) ? 'page' : undefined"
            :aria-disabled="!item.to || undefined"
          >
            <component :is="item.icon" class="sidebar__icon" aria-hidden="true" />
            <span class="sidebar__label">{{ item.label }}</span>
            <ProBadge v-if="item.pro" />
          </component>
        </li>
      </ul>
    </div>
  </nav>
</template>

<style scoped>
.sidebar {
  position: sticky;
  inset-block-start: 0;
  height: 100dvh;
  background-color: var(--sf-bg-surface);
  border-inline-end: 1px solid var(--sf-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: width var(--sf-duration-slow) var(--sf-ease-in-out);
}

.sidebar__brand {
  display: flex;
  align-items: center;
  gap: var(--sf-space-3);
  padding: var(--sf-space-4);
  height: var(--sf-topbar-height);
  border-block-end: 1px solid var(--sf-border);
  flex-shrink: 0;
}

.sidebar__logo {
  font-size: var(--sf-text-xl);
  color: var(--sf-accent);
  flex-shrink: 0;
}

.sidebar__wordmark {
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
  letter-spacing: var(--sf-tracking-tight);
  white-space: nowrap;
}

.sidebar__sections {
  flex: 1;
  overflow-y: auto;
  padding: var(--sf-space-3) var(--sf-space-2);
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
}

.sidebar__nav {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-0-5);
}

.sidebar__nav + .sidebar__nav {
  border-block-start: 1px solid var(--sf-border);
  padding-block-start: var(--sf-space-2);
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: var(--sf-space-3);
  padding: var(--sf-space-2);
  border-radius: var(--sf-radius-md);
  text-decoration: none;
  color: var(--sf-fg-secondary);
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-medium);
  white-space: nowrap;
  transition:
    background-color var(--sf-duration-base) var(--sf-ease-default),
    color var(--sf-duration-base) var(--sf-ease-default);
}

.sidebar__link:hover:not(.sidebar__link--disabled) {
  background-color: var(--sf-bg-subtle);
  color: var(--sf-fg-primary);
}

.sidebar__link--active {
  background-color: var(--sf-accent-subtle);
  color: var(--sf-fg-primary);
  border-inline-start: 2px solid var(--sf-accent);
}

.sidebar__link--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.sidebar__icon {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
}

.sidebar__label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
