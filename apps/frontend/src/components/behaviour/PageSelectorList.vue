<script setup lang="ts">
// =============================================================================
// PageSelectorList — searchable, sortable page picker (screens.md §2.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The bottom strip of the Heatmaps screen. Selecting a row re-scopes the whole
// view to that page. Search and sort are local (the breakdown is already loaded)
// and delegated to buildPageList so the behaviour is unit-tested in isolation.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Search } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import Input from '@/components/ui/input/Input.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { buildPageList, type PageSort } from './heatmapModel'
import type { BreakdownRow } from '@/api/types'

const props = withDefaults(
  defineProps<{ rows: BreakdownRow[]; selected: string; loading?: boolean }>(),
  { loading: false },
)

const emit = defineEmits<{ select: [pathname: string] }>()

const { t, n } = useI18n()

const search = ref('')
const sort = ref<PageSort>('clicks')

const sortOptions = computed(() => [
  { value: 'clicks', label: t('heatmaps.pages.mostClicks') },
  { value: 'path', label: t('heatmaps.pages.path') },
])

const entries = computed(() => buildPageList(props.rows, search.value, sort.value))
</script>

<template>
  <Card class="page-selector">
    <header class="page-selector__toolbar">
      <div class="page-selector__search">
        <Search class="page-selector__search-icon" aria-hidden="true" />
        <Input
          v-model="search"
          type="search"
          :placeholder="$t('heatmaps.pages.searchPlaceholder')"
          :aria-label="$t('heatmaps.pages.searchPlaceholder')"
          class="page-selector__search-input"
        />
      </div>
      <SegmentedControl
        v-model="sort"
        :options="sortOptions"
        :aria-label="$t('heatmaps.pages.sortBy')"
      />
    </header>

    <ul v-if="loading" class="page-selector__list" aria-busy="true">
      <li v-for="i in 4" :key="i" class="page-selector__row">
        <Skeleton class="h-4 w-full" />
      </li>
    </ul>

    <ul v-else-if="entries.length > 0" class="page-selector__list" role="listbox">
      <li v-for="entry in entries" :key="entry.pathname">
        <button
          type="button"
          role="option"
          :aria-selected="entry.pathname === selected"
          class="page-selector__row"
          :class="{ 'page-selector__row--active': entry.pathname === selected }"
          @click="emit('select', entry.pathname)"
        >
          <span class="page-selector__path" :title="entry.pathname">{{ entry.pathname }}</span>
          <span class="page-selector__count">
            {{ $t('heatmaps.pages.clicksCount', { count: n(entry.clicks, 'compact') }) }}
          </span>
          <span class="page-selector__bar">
            <ProgressBar :value="entry.barPct" :label="entry.pathname" />
          </span>
        </button>
      </li>
    </ul>

    <p v-else class="page-selector__empty">{{ $t('heatmaps.pages.noMatch') }}</p>
  </Card>
</template>

<style scoped>
.page-selector {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-4);
}

.page-selector__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--sf-space-3);
}

.page-selector__search {
  position: relative;
  flex: 1 1 240px;
  min-width: 200px;
}

.page-selector__search-icon {
  position: absolute;
  top: 50%;
  left: var(--sf-space-3);
  width: 1rem;
  height: 1rem;
  transform: translateY(-50%);
  color: var(--sf-fg-muted);
  pointer-events: none;
}

.page-selector__search-input {
  padding-left: calc(var(--sf-space-3) * 2 + 1rem);
}

.page-selector__list {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-1);
  margin: 0;
  padding: 0;
  list-style: none;
}

.page-selector__row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) auto minmax(80px, 1fr);
  align-items: center;
  gap: var(--sf-space-3);
  width: 100%;
  padding: var(--sf-space-2) var(--sf-space-3);
  border: 0;
  border-radius: var(--sf-radius-md);
  background: transparent;
  text-align: left;
  cursor: pointer;
  transition: background-color var(--sf-duration-fast) var(--sf-ease-default);
}

.page-selector__row:hover {
  background-color: var(--sf-bg-subtle);
}

.page-selector__row:focus-visible {
  outline: 2px solid var(--sf-border-focus);
  outline-offset: -2px;
}

.page-selector__row--active {
  background-color: var(--sf-accent-subtle);
}

.page-selector__path {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--sf-font-mono);
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-primary);
}

.page-selector__count {
  font-size: var(--sf-text-sm);
  font-variant-numeric: tabular-nums;
  color: var(--sf-fg-secondary);
  white-space: nowrap;
}

.page-selector__empty {
  margin: 0;
  padding: var(--sf-space-4) 0;
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-muted);
}

@media (width < 768px) {
  .page-selector__row {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .page-selector__bar {
    display: none;
  }
}
</style>
