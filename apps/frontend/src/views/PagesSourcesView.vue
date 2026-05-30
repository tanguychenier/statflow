<script setup lang="ts">
// =============================================================================
// PagesSourcesView — Pages & Sources (screens.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Tabbed breakdown of page performance and traffic acquisition. Six tabs (Pages,
// Entry, Exit, Sources, Referrers, UTM) each render a feature-rich table; the UTM
// tab fans out into Campaign / Medium / Source sub-tables. The active tab and UTM
// sub-tab round-trip through the URL (`?tab=…&utm=…`) so views are shareable. A
// global filter popover (the FilterBuilder) narrows every table at once. Each
// table is a self-contained PagesSourcesTablePanel owning its own search / sort /
// pagination, so this view stays a declarative composition layer.
// =============================================================================
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { Filter as FilterIcon } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Popover from '@/components/ui/popover/Popover.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import { Tabs, SegmentedControl } from '@/components/ui/tabs'
import { FilterBuilder } from '@/components/FilterBuilder'
import { PagesSourcesTablePanel } from '@/components/pagessources'
import {
  TABS,
  UTM_SUBTABS,
  resolveTab,
  resolveUtmSubTab,
  tabConfig,
  utmDimensionKey,
  type PagesSourcesTab,
  type TabConfig,
  type UtmSubTab,
} from '@/components/pagessources/tabs'
import type { Filter, FilterCombination } from '@/api/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

// ── Tab + URL sync ──────────────────────────────────────────────────────────
const activeTab = computed<PagesSourcesTab>(() => resolveTab(route.query.tab))
const activeUtm = computed<UtmSubTab>(() => resolveUtmSubTab(route.query.utm))

const tabItems = computed(() => TABS.map((tab) => ({ value: tab.id, label: t(tab.labelKey) })))

const utmItems = computed(() =>
  UTM_SUBTABS.map((sub) => ({ value: sub, label: t(utmDimensionKey(sub)) })),
)

function selectTab(value: string) {
  const next = resolveTab(value)
  if (next === activeTab.value) return
  router.replace({ query: { ...route.query, tab: next } })
}

function selectUtm(value: string) {
  const next = resolveUtmSubTab(value)
  router.replace({ query: { ...route.query, tab: 'utm', utm: next } })
}

// ── Global filters ───────────────────────────────────────────────────────────
const filters = ref<Filter[]>([])
const filterCombination = ref<FilterCombination>('and')
const filterOpen = ref(false)

const activeFilterCount = computed(() => filters.value.length)

// Synthesise a TabConfig for the active UTM sub-dimension so the panel renders
// the right column header / search placeholder / detail filter property.
const utmConfig = computed<TabConfig>(() => ({
  id: 'utm',
  labelKey: 'pagesSources.tabs.utm',
  property: activeUtm.value,
  dimensionLabelKey: utmDimensionKey(activeUtm.value),
  searchPlaceholderKey: 'pagesSources.search.utm',
}))

// Keep the URL tidy: drop the `utm` param when not on the UTM tab.
watch(activeTab, (tab) => {
  if (tab !== 'utm' && route.query.utm !== undefined) {
    const nextQuery = { ...route.query }
    delete nextQuery.utm
    router.replace({ query: nextQuery })
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-semibold tracking-tight text-fg-primary">
        {{ t('pagesSources.title') }}
      </h1>

      <Popover
        :open="filterOpen"
        align="end"
        class="w-md max-w-[calc(100vw-2rem)]"
        @update:open="filterOpen = $event"
      >
        <template #trigger>
          <Button variant="outline" size="sm">
            <template #leading><FilterIcon class="size-4" aria-hidden="true" /></template>
            {{ t('pagesSources.filters.add') }}
            <Badge v-if="activeFilterCount > 0" variant="accent" class="ml-1">
              {{ activeFilterCount }}
            </Badge>
          </Button>
        </template>
        <FilterBuilder
          :filters="filters"
          :combination="filterCombination"
          @update:filters="filters = $event"
          @update:combination="filterCombination = $event"
        />
      </Popover>
    </div>

    <!--
      Only the active tab's panel is rendered, so a single breakdown query fires
      at a time and switching tabs cleanly swaps data (screens.md §5.2). The
      dynamic slot name targets the matching TabsContent inside the Tabs wrapper.
    -->
    <Tabs :model-value="activeTab" :items="tabItems" @update:model-value="selectTab">
      <template #[activeTab]>
        <div v-if="activeTab === 'utm'" class="flex flex-col gap-4">
          <SegmentedControl
            :model-value="activeUtm"
            :options="utmItems"
            :aria-label="t('pagesSources.tabs.utm')"
            @update:model-value="selectUtm"
          />
          <PagesSourcesTablePanel
            :key="activeUtm"
            :config="utmConfig"
            :filters="filters"
            utm
            :export-slug="activeUtm"
          />
        </div>
        <PagesSourcesTablePanel
          v-else
          :key="activeTab"
          :config="tabConfig(activeTab)"
          :filters="filters"
          :export-slug="activeTab"
        />
      </template>
    </Tabs>
  </div>
</template>
