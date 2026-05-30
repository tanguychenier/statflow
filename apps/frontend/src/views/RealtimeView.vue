<script setup lang="ts">
// =============================================================================
// RealtimeView — live active-user view (screens.md §4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Subscribes to the realtime SSE stream for the current site and renders a live
// dashboard: four KPI tiles (active users / top page / events-per-minute /
// mobile share), a 30-minute active-users trend bar chart, three ranked live
// lists (countries, top pages, top referrers) that FLIP-reorder as the rankings
// shift, and a pausable live event log. The canonical active window is the last
// 5 minutes (openapi.yaml); the trend chart shows a wider 30-minute context. All
// derivation is delegated to the pure helpers in ./components/realtime so this
// file stays a declarative composition layer.
// =============================================================================
import { computed, onMounted, onUnmounted, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'

import { EmptyState } from '@/components/ui'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { countryFlag, countryName } from '@/components/overview/countries'

import {
  RealtimeKpiCard,
  RealtimeTrendChart,
  RealtimeBreakdownList,
  LiveEventStream,
  RealtimeStatusPill,
  useRealtimeStream,
  appendEvent,
  buildTrend,
  eventsPerMinute,
  mobileSharePct,
  topCountries,
  topPageLabel,
  topPagesRows,
  topReferrers,
  MAX_EVENTS_DESKTOP,
  MAX_EVENTS_MOBILE,
  type LiveEventRow,
  type RankedRow,
} from '@/components/realtime'

const { t, n } = useI18n()

const siteStore = useCurrentSiteStore()
const { currentSiteId, hasSite } = storeToRefs(siteStore)

// ── SSE stream ───────────────────────────────────────────────────────────────
const { stats, status, onEvent, lastStatsAt, connect, disconnect } = useRealtimeStream(currentSiteId)

// ── Live event buffer ────────────────────────────────────────────────────────
// Two viewport caps (§4.3): 200 rows on desktop, 100 on mobile. The buffer is a
// shallowRef of an immutable array so each append is a cheap reference swap.
const mobileQuery = window.matchMedia('(max-width: 767px)')
const isMobile = ref(mobileQuery.matches)
function onViewportChange(event: MediaQueryListEvent) {
  isMobile.value = event.matches
}

const maxEvents = computed(() => (isMobile.value ? MAX_EVENTS_MOBILE : MAX_EVENTS_DESKTOP))

const events = shallowRef<LiveEventRow[]>([])
let sequence = 0

// Pause freezes the *visible* rows; new events keep buffering behind a counter.
const paused = ref(false)
const pausedSnapshot = shallowRef<LiveEventRow[]>([])
const bufferedSincePause = ref(0)

onEvent((event) => {
  sequence += 1
  events.value = appendEvent(events.value, event, sequence, maxEvents.value)
  if (paused.value) bufferedSincePause.value += 1
})

function togglePause() {
  if (paused.value) {
    paused.value = false
    bufferedSincePause.value = 0
    pausedSnapshot.value = []
  } else {
    pausedSnapshot.value = events.value
    paused.value = true
    bufferedSincePause.value = 0
  }
}

const visibleEvents = computed(() => (paused.value ? pausedSnapshot.value : events.value))

// ── Derived KPIs (5-minute window, recomputed off a 1s clock) ────────────────
const nowTick = ref(Date.now())
let clock: ReturnType<typeof setInterval> | null = null

const activeVisitors = computed(() => stats.value?.current_visitors ?? 0)
const topPage = computed(() => topPageLabel(stats.value))
const eventsPerMin = computed(() => eventsPerMinute(events.value, nowTick.value))
const mobilePct = computed(() => mobileSharePct(events.value, nowTick.value))

const TOP_N = 6
const pagesRows = computed<RankedRow[]>(() => topPagesRows(stats.value, TOP_N))
const countriesRows = computed<RankedRow[]>(() => topCountries(events.value, nowTick.value, TOP_N))
const referrerRows = computed<RankedRow[]>(() => topReferrers(events.value, nowTick.value, TOP_N))
const trendBuckets = computed(() => buildTrend(events.value, nowTick.value))

function countryFlagFor(row: RankedRow): string {
  return countryFlag(row.key)
}
function countryRowLabel(row: RankedRow): RankedRow {
  return { ...row, label: countryName(row.key) }
}
const countriesDisplay = computed(() => countriesRows.value.map(countryRowLabel))

const fmtInt = (value: number) => n(value, 'integer')
const fmtPct = (value: number) => n(value / 100, 'percentWhole')

const isError = computed(() => status.value === 'error')
const isConnecting = computed(
  () => status.value === 'connecting' || status.value === 'reconnecting' || status.value === 'idle',
)
const isLoadingFirstStats = computed(() => stats.value === null && !isError.value)

onMounted(() => {
  mobileQuery.addEventListener('change', onViewportChange)
  clock = setInterval(() => {
    nowTick.value = Date.now()
  }, 1_000)
  connect()
})

onUnmounted(() => {
  mobileQuery.removeEventListener('change', onViewportChange)
  if (clock !== null) clearInterval(clock)
  disconnect()
})

function retry() {
  connect()
}
</script>

<template>
  <div class="realtime">
    <header class="realtime__header">
      <h1 class="realtime__title">{{ t('realtime.title') }}</h1>
      <RealtimeStatusPill :status="status" :last-stats-at="lastStatsAt" />
    </header>

    <EmptyState
      v-if="!hasSite"
      variant="no-setup"
      :title="t('realtime.noSite.title')"
      :description="t('realtime.noSite.description')"
    />

    <template v-else>
      <!-- KPI tiles -->
      <section class="realtime__kpis" :aria-label="t('realtime.kpis.aria')">
        <RealtimeKpiCard
          :label="t('realtime.activeUsers')"
          :value="fmtInt(activeVisitors)"
          :loading="isLoadingFirstStats"
          pulse
          live
        />
        <RealtimeKpiCard
          :label="t('realtime.kpis.topPage')"
          :value="topPage ?? '—'"
          :loading="isLoadingFirstStats"
          mono
        />
        <RealtimeKpiCard
          :label="t('realtime.kpis.eventsPerMinute')"
          :value="fmtInt(eventsPerMin)"
        />
        <RealtimeKpiCard
          :label="t('realtime.kpis.mobileShare')"
          :value="fmtPct(mobilePct)"
        />
      </section>

      <!-- Trend + countries -->
      <section class="realtime__row" :aria-label="t('realtime.trend.title')">
        <div class="realtime__panel">
          <h2 class="realtime__panel-title">{{ t('realtime.trend.title') }}</h2>
          <RealtimeTrendChart
            :title="t('realtime.trend.title')"
            :buckets="trendBuckets"
            :loading="isConnecting && events.length === 0"
            :error="isError && events.length === 0"
            @retry="retry"
          />
        </div>
        <RealtimeBreakdownList
          :title="t('realtime.countries')"
          :rows="countriesDisplay"
          :metric-label="t('realtime.activeUsers')"
          :leading-for="countryFlagFor"
          :loading="isLoadingFirstStats && countriesDisplay.length === 0"
          :error="isError && countriesDisplay.length === 0"
          @retry="retry"
        />
      </section>

      <!-- Top pages + top referrers -->
      <section class="realtime__row" :aria-label="t('realtime.topPages')">
        <RealtimeBreakdownList
          :title="t('realtime.topPages')"
          :rows="pagesRows"
          :metric-label="t('realtime.activeUsers')"
          :loading="isLoadingFirstStats"
          :error="isError && pagesRows.length === 0"
          @retry="retry"
        />
        <RealtimeBreakdownList
          :title="t('realtime.topReferrers')"
          :rows="referrerRows"
          :metric-label="t('realtime.activeUsers')"
          :loading="isLoadingFirstStats"
          :error="isError && referrerRows.length === 0"
          @retry="retry"
        />
      </section>

      <!-- Live event stream -->
      <LiveEventStream
        :rows="visibleEvents"
        :paused="paused"
        :buffered-count="bufferedSincePause"
        :loading="isConnecting && events.length === 0"
        @toggle-pause="togglePause"
      />
    </template>
  </div>
</template>

<style scoped>
.realtime {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-6);
}

.realtime__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--sf-space-3);
}

.realtime__title {
  margin: 0;
  font-size: var(--sf-text-xl);
  font-weight: var(--sf-weight-semibold);
  letter-spacing: var(--sf-tracking-tight);
  color: var(--sf-fg-primary);
}

.realtime__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--sf-space-4);
}

.realtime__row {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: var(--sf-space-4);
  align-items: start;
}

.realtime__row + .realtime__row {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.realtime__panel {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-3);
  padding: var(--sf-space-5) var(--sf-space-6);
  background-color: var(--sf-bg-surface);
  border: 1px solid var(--sf-border);
  border-radius: var(--sf-radius-xl);
  box-shadow: var(--sf-shadow-sm);
}

.realtime__panel-title {
  margin: 0;
  font-size: var(--sf-text-base);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

@media (width < 1024px) {
  .realtime__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .realtime__row,
  .realtime__row + .realtime__row {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (width < 768px) {
  .realtime__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
