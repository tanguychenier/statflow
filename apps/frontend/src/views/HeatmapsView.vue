<script setup lang="ts">
// =============================================================================
// HeatmapsView — Behaviour: Heatmaps & Click Maps (Screen 2, screens.md §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Click / scroll / movement / rage-click overlay on a page wireframe, with a
// stats panel (top elements + scroll depth), rage- & dead-click insights, and a
// searchable page selector — all filterable by saved segment. The overlay is a
// canvas painted from percentage buckets (HeatmapOverlay); intensity/radius are
// client-side only. The screen is driven by the global site + date-range stores.
// =============================================================================
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Maximize2, Minimize2 } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'

import { Button } from '@/components/ui'
import Card from '@/components/ui/card/Card.vue'
import Select from '@/components/ui/select/Select.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import { ChartEmpty, ChartSkeleton, HeatmapOverlay } from '@/components/charts'

import {
  BehaviourInsights,
  BehaviourStatsPanel,
  PageSelectorList,
  PageWireframe,
  RangeSlider,
  ScrollDepthOverlay,
  HEATMAP_VIEW_TYPES,
  DEVICE_KINDS,
  apiHeatmapTypeFor,
  buildInsights,
  eventNameFilterFor,
  foldDepthPct,
  sampleSizeFromPoints,
  scrollDepthBars,
  showsClickOverlay,
  topElementsFromBreakdown,
  viewportRangeFor,
  type DeviceKind,
  type HeatmapViewType,
} from '@/components/behaviour'

import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'
import { useHeatmap, useBreakdown } from '@/api/composables/useAnalytics'
import { useSegments } from '@/api/composables/useSites'
import type { BreakdownQuery, Filter, HeatmapQueryRequest } from '@/api/types'

const { t } = useI18n()

const siteStore = useCurrentSiteStore()
const dateStore = useDateRangeStore()
const { currentSiteId } = storeToRefs(siteStore)
const { apiRange } = storeToRefs(dateStore)

// ── Local screen state ──────────────────────────────────────────────────────
const viewType = ref<HeatmapViewType>('clicks')
const device = ref<DeviceKind>('desktop')
const pathname = ref('/')
const segmentId = ref<string>('')
const intensity = ref(60)
const radius = ref(30)
const fullscreen = ref(false)

// ── Option lists for the toolbar ────────────────────────────────────────────
const viewTypeOptions = computed(() =>
  HEATMAP_VIEW_TYPES.map((value) => ({
    value,
    label: t(`heatmaps.viewTypes.${value === 'rage' ? 'rageClicks' : value}`),
  })),
)

const deviceOptions = computed(() =>
  DEVICE_KINDS.map((value) => ({ value, label: t(`heatmaps.devices.${value}`) })),
)

const segmentsQuery = useSegments(currentSiteId)
const segmentOptions = computed(() => [
  { value: '', label: t('heatmaps.allVisitors') },
  ...(segmentsQuery.data.value ?? []).map((segment) => ({
    value: segment.id,
    label: segment.name,
  })),
])

const activeSegmentId = computed(() => (segmentId.value === '' ? undefined : segmentId.value))

// ── Heatmap overlay query (click / scroll buckets) ──────────────────────────
// The heatmap endpoint accepts no filters or segment (openapi.yaml), so segment
// scoping is applied to the companion breakdown/insight queries below; the
// overlay reflects the whole audience for the page + viewport + type.
const heatmapQuery = computed<HeatmapQueryRequest | null>(() => {
  if (!currentSiteId.value) return null
  return {
    date_from: apiRange.value.date_from,
    date_to: apiRange.value.date_to,
    pathname: pathname.value,
    heatmap_type: apiHeatmapTypeFor(viewType.value),
    ...viewportRangeFor(device.value),
  }
})
const heatmap = useHeatmap(currentSiteId, heatmapQuery)

const clickPoints = computed(() => heatmap.data.value?.click_points ?? [])
const scrollBars = computed(() => scrollDepthBars(heatmap.data.value?.scroll_depths))
const fold = computed(() => foldDepthPct(scrollBars.value))
const sampleSize = computed(() => sampleSizeFromPoints(clickPoints.value))

const overlayEmpty = computed(
  () =>
    !heatmap.isLoading.value &&
    !heatmap.isError.value &&
    (showsClickOverlay(viewType.value)
      ? clickPoints.value.length === 0
      : scrollBars.value.length === 0),
)

// ── Top-elements breakdown (scoped to the active view + segment) ─────────────
function elementBreakdown(eventFilter: Filter | null): BreakdownQuery {
  const filters: Filter[] = [{ property: 'pathname', operator: 'eq', value: pathname.value }]
  if (eventFilter) filters.push(eventFilter)
  return {
    date_from: apiRange.value.date_from,
    date_to: apiRange.value.date_to,
    property: 'element_selector',
    metrics: ['events'],
    sort_by: 'events',
    sort_order: 'desc',
    limit: 5,
    segment_id: activeSegmentId.value,
    filters,
  }
}

const topElementsQuery = computed(() => elementBreakdown(eventNameFilterFor(viewType.value)))
const topElementsBreakdown = useBreakdown(currentSiteId, topElementsQuery)
const topElements = computed(() =>
  topElementsFromBreakdown(topElementsBreakdown.data.value?.rows ?? []),
)

// ── Frustration insights (always rage + dead, independent of overlay) ───────
const rageQuery = computed(() =>
  elementBreakdown({ property: 'event_name', operator: 'eq', value: 'rage_click' }),
)
const deadQuery = computed(() =>
  elementBreakdown({ property: 'event_name', operator: 'eq', value: 'dead_click' }),
)
const rageBreakdown = useBreakdown(currentSiteId, rageQuery)
const deadBreakdown = useBreakdown(currentSiteId, deadQuery)
const rageInsights = computed(() => buildInsights(rageBreakdown.data.value?.rows ?? [], 'rage'))
const deadInsights = computed(() => buildInsights(deadBreakdown.data.value?.rows ?? [], 'dead'))

// ── Page selector breakdown (pathname × clicks) ─────────────────────────────
const pageListQuery = computed<BreakdownQuery>(() => ({
  date_from: apiRange.value.date_from,
  date_to: apiRange.value.date_to,
  property: 'pathname',
  metrics: ['events'],
  sort_by: 'events',
  sort_order: 'desc',
  limit: 50,
  segment_id: activeSegmentId.value,
  filters: [{ property: 'event_name', operator: 'eq', value: 'click' }],
}))
const pageListBreakdown = useBreakdown(currentSiteId, pageListQuery)
const pageRows = computed(() => pageListBreakdown.data.value?.rows ?? [])

// Land on the busiest page once the list resolves and the default is still set.
watch(pageRows, (rows) => {
  if (pathname.value === '/' && rows.length > 0 && rows[0].value) {
    pathname.value = rows[0].value
  }
})

function selectPage(next: string) {
  pathname.value = next
}

function selectDevice(next: string) {
  device.value = next as DeviceKind
}

function selectViewType(next: string) {
  viewType.value = next as HeatmapViewType
}

function selectSegment(next: string) {
  segmentId.value = next
}

function toggleFullscreen() {
  fullscreen.value = !fullscreen.value
}

const totalEvents = computed(() => heatmap.data.value?.total_events ?? 0)
const pageSelectOptions = computed(() =>
  pageRows.value.map((row) => ({ value: row.value, label: row.value })),
)

const formatPercent = (value: number) => `${value}%`
const formatPixels = (value: number) => `${value}px`
</script>

<template>
  <div class="heatmaps" :class="{ 'heatmaps--fullscreen': fullscreen }">
    <header class="heatmaps__header">
      <h1 class="heatmaps__title">{{ t('heatmaps.title') }}</h1>
      <div class="heatmaps__toolbar">
        <Select
          :model-value="pathname"
          :options="pageSelectOptions"
          :placeholder="t('heatmaps.pages.searchPlaceholder')"
          :aria-label="t('heatmaps.pageLabel')"
          @update:model-value="selectPage"
        />
        <Select
          :model-value="device"
          :options="deviceOptions"
          :aria-label="t('heatmaps.deviceLabel')"
          @update:model-value="selectDevice"
        />
        <Select
          :model-value="segmentId"
          :options="segmentOptions"
          :aria-label="t('heatmaps.segmentLabel')"
          @update:model-value="selectSegment"
        />
      </div>
    </header>

    <SegmentedControl
      :model-value="viewType"
      :options="viewTypeOptions"
      :aria-label="t('heatmaps.viewTypeLabel')"
      @update:model-value="selectViewType"
    />

    <div class="heatmaps__main">
      <Card class="heatmaps__preview">
        <div class="heatmaps__preview-head">
          <p class="heatmaps__page" :title="pathname">{{ pathname }}</p>
          <Button variant="ghost" size="sm" :aria-pressed="fullscreen" @click="toggleFullscreen">
            <template #leading>
              <Maximize2 v-if="!fullscreen" class="size-4" aria-hidden="true" />
              <Minimize2 v-else class="size-4" aria-hidden="true" />
            </template>
            {{ fullscreen ? t('heatmaps.exitFullscreen') : t('heatmaps.openFullscreen') }}
          </Button>
        </div>

        <div class="heatmaps__stage">
          <ChartSkeleton v-if="heatmap.isLoading.value" :height="420" />

          <ChartEmpty
            v-else-if="heatmap.isError.value"
            variant="error"
            :height="420"
            @retry="heatmap.refetch()"
          />

          <ChartEmpty
            v-else-if="overlayEmpty"
            :height="420"
            :title="t('heatmaps.empty.title')"
            :description="t('heatmaps.empty.description')"
          />

          <div v-else class="heatmaps__canvas">
            <PageWireframe :device="device" />
            <HeatmapOverlay
              v-if="showsClickOverlay(viewType)"
              class="heatmaps__overlay"
              :screenshot-url="''"
              :points="clickPoints"
              :intensity="intensity / 100"
              :radius="radius"
            />
            <ScrollDepthOverlay v-else :bars="scrollBars" :fold-depth-pct="fold" />
          </div>
        </div>

        <div v-if="showsClickOverlay(viewType)" class="heatmaps__sliders">
          <RangeSlider
            v-model="intensity"
            :label="t('heatmaps.intensity')"
            :min="0"
            :max="100"
            :format="formatPercent"
          />
          <RangeSlider
            v-model="radius"
            :label="t('heatmaps.radius')"
            :min="10"
            :max="80"
            :format="formatPixels"
          />
        </div>
      </Card>

      <BehaviourStatsPanel
        :total-events="totalEvents"
        :unique-users="null"
        :sample-size="sampleSize"
        :top-elements="topElements"
        :scroll-depth="scrollBars"
        :loading="heatmap.isLoading.value || topElementsBreakdown.isLoading.value"
      />
    </div>

    <BehaviourInsights
      :rage="rageInsights"
      :dead="deadInsights"
      :loading="rageBreakdown.isLoading.value || deadBreakdown.isLoading.value"
    />

    <PageSelectorList
      :rows="pageRows"
      :selected="pathname"
      :loading="pageListBreakdown.isLoading.value"
      @select="selectPage"
    />
  </div>
</template>

<style scoped>
.heatmaps {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-6);
}

.heatmaps--fullscreen {
  position: fixed;
  inset: 0;
  z-index: 50;
  padding: var(--sf-space-6);
  overflow: auto;
  background: var(--sf-bg-base);
}

.heatmaps__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--sf-space-4);
}

.heatmaps__title {
  margin: 0;
  font-size: var(--sf-text-xl);
  font-weight: var(--sf-weight-semibold);
  letter-spacing: var(--sf-tracking-tight);
  color: var(--sf-fg-primary);
}

.heatmaps__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sf-space-2);
}

.heatmaps__main {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: var(--sf-space-6);
  align-items: start;
}

.heatmaps__preview {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-4);
}

.heatmaps__preview-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sf-space-3);
}

.heatmaps__page {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--sf-font-mono);
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-primary);
}

.heatmaps__stage {
  position: relative;
  width: 100%;
  max-height: 70vh;
  overflow: auto;
  border-radius: var(--sf-radius-lg);
  background: var(--sf-bg-subtle);
}

.heatmaps__canvas {
  position: relative;
}

.heatmaps__overlay {
  position: absolute;
  inset: 0;
}

.heatmaps__sliders {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sf-space-6);
}

@media (width < 1024px) {
  .heatmaps__main {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (width < 768px) {
  .heatmaps__sliders {
    grid-template-columns: 1fr;
  }
}
</style>
