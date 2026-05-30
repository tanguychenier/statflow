<script setup lang="ts">
// =============================================================================
// OverviewView — at-a-glance property health (screens.md §1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Orchestrates the Overview dashboard: five KPI cards, the sessions/visitors
// time-series, and four breakdown cards (Top pages, Sources, Devices,
// Geography). Every panel is fed by a @tanstack/vue-query query keyed on the
// current site + global date range, so changing either re-fetches everything
// with skeleton states (screens.md "Global date range propagation"). All data
// shaping is delegated to the pure adapters under ./components/overview so this
// file stays a declarative composition layer.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { MetricCard } from '@/components/MetricCard'
import { TimeSeriesChart, DonutChart, GeoMap, ChartCard } from '@/components/charts'
import BreakdownPanel from '@/components/overview/BreakdownPanel.vue'
import OverviewToolbar from '@/components/overview/OverviewToolbar.vue'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'
import { useAggregateMetrics, useTimeSeries, useBreakdown } from '@/api/composables/useAnalytics'
import { formatDuration } from '@/utils/formatDuration'
import {
  buildOverviewMetrics,
  OVERVIEW_METRICS,
  type MetricFormatters,
} from '@/components/overview/overviewMetrics'
import { buildOverviewSeries } from '@/components/overview/overviewSeries'
import {
  buildBreakdownRows,
  buildDonutSlices,
  buildGeoData,
} from '@/components/overview/breakdownTable'
import { countryName } from '@/components/overview/countries'
import {
  buildOverviewCsv,
  downloadCsv,
  type ExportSection,
} from '@/components/overview/overviewExport'
import { toast } from '@/composables/useToast'
import type { MetricName, TimeInterval } from '@/api/types'

const { t, n } = useI18n()

const siteStore = useCurrentSiteStore()
const dateRangeStore = useDateRangeStore()
const { currentSiteId } = storeToRefs(siteStore)
const { apiRange, interval, compareEnabled, compareValue } = storeToRefs(dateRangeStore)

// Granularity defaults to the range-derived bucket but the toolbar can override
// it locally without touching the global range.
const intervalOverride = ref<TimeInterval | null>(null)
const activeInterval = computed<TimeInterval>(() => intervalOverride.value ?? interval.value)

const TOP_N = 5
const requestedMetrics: MetricName[] = [...OVERVIEW_METRICS]

const aggregateQuery = computed(() => ({
  ...apiRange.value,
  metrics: requestedMetrics,
  compare_period: compareValue.value,
}))

const seriesQuery = computed(() => ({
  ...apiRange.value,
  interval: activeInterval.value,
  metrics: ['visitors', 'sessions', 'pageviews'] as MetricName[],
}))

function breakdownQuery(property: string) {
  return computed(() => ({
    ...apiRange.value,
    property,
    limit: TOP_N,
    sort_by: 'visitors' as MetricName,
    sort_order: 'desc' as const,
  }))
}

const aggregate = useAggregateMetrics(currentSiteId, aggregateQuery)
const series = useTimeSeries(currentSiteId, seriesQuery)
const pages = useBreakdown(currentSiteId, breakdownQuery('pathname'))
const sources = useBreakdown(currentSiteId, breakdownQuery('referrer_domain'))
const devices = useBreakdown(currentSiteId, breakdownQuery('device_type'))
const countries = useBreakdown(currentSiteId, breakdownQuery('country'))

const formatters = computed<MetricFormatters>(() => ({
  count: (value: number) => n(value, 'integer'),
  percent: (value: number) => n(value / 100, 'percent'),
  duration: (value: number) => formatDuration(value),
}))

const metricCards = computed(() =>
  buildOverviewMetrics(aggregate.data.value, formatters.value, series.data.value),
)

const comparisonLabel = computed(() =>
  t('overview.comparison.label', { period: t('overview.comparison.prevPeriod') }),
)

const chartData = computed(() =>
  buildOverviewSeries(series.data.value, {
    metric: 'visitors',
    currentLabel: t('overview.metrics.users'),
    comparisonLabel: comparisonLabel.value,
  }),
)

const deviceLabels: Record<string, string> = {
  desktop: 'Desktop',
  mobile: 'Mobile',
  tablet: 'Tablet',
}
function deviceLabel(value: string): string {
  return deviceLabels[value] ?? value
}

const pageRows = computed(() => buildBreakdownRows(pages.data.value, { metric: 'visitors' }))
const sourceRows = computed(() => buildBreakdownRows(sources.data.value, { metric: 'visitors' }))
const countryRows = computed(() =>
  buildBreakdownRows(countries.data.value, { metric: 'visitors', labelFor: countryName }),
)
const deviceSlices = computed(() => buildDonutSlices(devices.data.value, deviceLabel))
const geoData = computed(() => buildGeoData(countries.data.value, countryName))

function onInterval(next: TimeInterval) {
  intervalOverride.value = next
}

function onCompare(enabled: boolean) {
  if (enabled !== compareEnabled.value) dateRangeStore.toggleCompare()
}

const exporting = ref(false)
function onExport() {
  exporting.value = true
  try {
    const sections: ExportSection[] = [
      {
        title: t('overview.charts.topPages'),
        dimensionHeader: t('dimensions.pathname'),
        metricHeader: t('overview.metrics.users'),
        rows: pageRows.value,
      },
      {
        title: t('overview.charts.trafficSources'),
        dimensionHeader: t('dimensions.referrer_domain'),
        metricHeader: t('overview.metrics.users'),
        rows: sourceRows.value,
      },
      {
        title: t('overview.charts.geography'),
        dimensionHeader: t('dimensions.country'),
        metricHeader: t('overview.metrics.users'),
        rows: countryRows.value,
      },
    ]
    const csv = buildOverviewCsv({
      metricsTitle: t('overview.title'),
      metrics: metricCards.value.map((card) => ({ label: t(card.titleKey), value: card.value })),
      sections,
    })
    const stamp = `${apiRange.value.date_from}_${apiRange.value.date_to}`
    const ok = downloadCsv(`statflow-overview-${stamp}.csv`, csv)
    if (ok) toast.success(t('overview.export.success'))
  } finally {
    exporting.value = false
  }
}

const pagesRoute = '/pages-sources'
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-semibold tracking-tight text-fg-primary">
        {{ t('overview.title') }}
      </h1>
    </div>

    <OverviewToolbar
      :interval="activeInterval"
      :compare-enabled="compareEnabled"
      :exporting="exporting"
      @update:interval="onInterval"
      @update:compare="onCompare"
      @export="onExport"
    />

    <!-- KPI cards -->
    <section
      class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5"
      :aria-label="t('overview.metrics.aria')"
    >
      <MetricCard
        v-for="card in metricCards"
        :key="card.id"
        :title="t(card.titleKey)"
        :value="card.value"
        :trend="card.trend"
        :spark-data="card.sparkData"
        :loading="aggregate.isPending.value"
      />
    </section>

    <!-- Main time series -->
    <section :aria-label="t('overview.charts.sessionsOverTime')">
      <ChartCard :title="t('overview.charts.sessionsOverTime')">
        <TimeSeriesChart
          :title="t('overview.charts.sessionsOverTime')"
          :categories="chartData.categories"
          :series="chartData.series"
          :loading="series.isPending.value"
          :error="series.isError.value"
          :stale="series.isFetching.value && !series.isPending.value"
          :height="320"
          @retry="series.refetch()"
        />
      </ChartCard>
    </section>

    <!-- Top pages + Traffic sources -->
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2" :aria-label="t('overview.charts.topPages')">
      <BreakdownPanel
        :title="t('overview.charts.topPages')"
        :rows="pageRows"
        :metric-label="t('overview.metrics.users')"
        :loading="pages.isPending.value"
        :error="pages.isError.value"
        :view-all-to="pagesRoute"
        @retry="pages.refetch()"
      />
      <BreakdownPanel
        :title="t('overview.charts.trafficSources')"
        :rows="sourceRows"
        :metric-label="t('overview.metrics.users')"
        :loading="sources.isPending.value"
        :error="sources.isError.value"
        :view-all-to="pagesRoute"
        @retry="sources.refetch()"
      />
    </section>

    <!-- Devices donut + Geography map -->
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2" :aria-label="t('overview.charts.devices')">
      <ChartCard :title="t('overview.charts.devices')">
        <DonutChart
          :title="t('overview.charts.devices')"
          :slices="deviceSlices"
          :loading="devices.isPending.value"
          :error="devices.isError.value"
          :stale="devices.isFetching.value && !devices.isPending.value"
          :height="260"
          @retry="devices.refetch()"
        />
      </ChartCard>

      <ChartCard :title="t('overview.charts.geography')">
        <GeoMap
          :title="t('overview.charts.geography')"
          :data="geoData"
          :loading="countries.isPending.value"
          :error="countries.isError.value"
          :height="300"
          @retry="countries.refetch()"
        />
      </ChartCard>
    </section>

    <!-- Top countries list (geography detail) -->
    <section :aria-label="t('overview.topCountries')">
      <BreakdownPanel
        :title="t('overview.topCountries')"
        :rows="countryRows"
        :metric-label="t('overview.metrics.users')"
        :loading="countries.isPending.value"
        :error="countries.isError.value"
        show-flag
        @retry="countries.refetch()"
      />
    </section>
  </div>
</template>
