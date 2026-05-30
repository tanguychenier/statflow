<script setup lang="ts">
// =============================================================================
// FunnelDetail — single-funnel analysis (screens.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Composes the funnel analysis: header stats, the stepped conversion chart, a
// conversion-per-group breakdown, and a conversion-over-time trend. Each panel
// is fed by a vue-query query keyed on the current site + global date range +
// this funnel, so date / site changes re-fetch everything with skeletons. Data
// shaping is delegated to the pure adapters so this stays a composition layer.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ArrowLeft, Pencil } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import { ChartCard, FunnelChart, TimeSeriesChart } from '@/components/charts'
import FunnelSummaryStats from './FunnelSummaryStats.vue'
import FunnelBreakdownPanel from './FunnelBreakdownPanel.vue'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useDateRangeStore } from '@/stores/dateRange'
import { useFunnelQuery, useBreakdown, useTimeSeries } from '@/api/composables/useAnalytics'
import { buildFunnelSummary } from './funnelStats'
import { buildFunnelBreakdown, type FunnelBreakdownDimension } from './funnelBreakdown'
import { buildOverviewSeries } from '@/components/overview/overviewSeries'
import { countryName } from '@/components/overview/countries'
import { goalFilterForFunnel } from './funnelGoalFilter'
import type { Funnel, Filter, MetricName } from '@/api/types'

const props = defineProps<{ funnel: Funnel }>()

const emit = defineEmits<{ back: []; edit: [] }>()

const { t } = useI18n()

const siteStore = useCurrentSiteStore()
const dateRangeStore = useDateRangeStore()
const { currentSiteId } = storeToRefs(siteStore)
const { apiRange, interval } = storeToRefs(dateRangeStore)

const breakdownDimension = ref<FunnelBreakdownDimension>('device_type')

// Scope the breakdown / trend to the funnel's conversion event so the secondary
// panels describe *this* funnel rather than all site traffic.
const goalFilter = computed<Filter | null>(() => goalFilterForFunnel(props.funnel))
const scopedFilters = computed<Filter[]>(() =>
  goalFilter.value ? [goalFilter.value] : [],
)

const funnelQuery = computed(() => ({
  ...apiRange.value,
  funnel_id: props.funnel.id,
}))

const breakdownQuery = computed(() => ({
  ...apiRange.value,
  property: breakdownDimension.value,
  metrics: ['conversion_rate'] as MetricName[],
  sort_by: 'conversion_rate' as MetricName,
  sort_order: 'desc' as const,
  limit: 8,
  filters: scopedFilters.value,
}))

const trendQuery = computed(() => ({
  ...apiRange.value,
  interval: interval.value,
  metrics: ['conversion_rate'] as MetricName[],
  filters: scopedFilters.value,
}))

const analysis = useFunnelQuery(currentSiteId, funnelQuery)
const breakdown = useBreakdown(currentSiteId, breakdownQuery)
const trend = useTimeSeries(currentSiteId, trendQuery)

const summary = computed(() => buildFunnelSummary(analysis.data.value))
const steps = computed(() => analysis.data.value?.steps ?? [])

const breakdownData = computed(() =>
  buildFunnelBreakdown(breakdown.data.value, {
    metric: 'conversion_rate',
    labelFor: breakdownDimension.value === 'country' ? countryName : undefined,
  }),
)

const trendData = computed(() =>
  buildOverviewSeries(trend.data.value, {
    metric: 'conversion_rate',
    currentLabel: t('funnels.trend.series'),
    comparisonLabel: t('funnels.trend.series'),
  }),
)
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <nav class="flex items-center gap-2 text-sm" :aria-label="t('funnels.breadcrumb')">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 text-fg-secondary hover:text-fg-primary"
          @click="emit('back')"
        >
          <ArrowLeft class="size-4" aria-hidden="true" />
          {{ t('funnels.title') }}
        </button>
        <span class="text-fg-muted" aria-hidden="true">/</span>
        <span class="font-semibold text-fg-primary">{{ funnel.name }}</span>
      </nav>

      <Button variant="outline" size="sm" @click="emit('edit')">
        <template #leading><Pencil class="size-4" aria-hidden="true" /></template>
        {{ t('funnels.editFunnel') }}
      </Button>
    </div>

    <FunnelSummaryStats :summary="summary" :loading="analysis.isPending.value" />

    <ChartCard :title="t('funnels.chart.title')">
      <FunnelChart
        :steps="steps"
        :loading="analysis.isPending.value"
        :error="analysis.isError.value"
        @retry="analysis.refetch()"
      />
    </ChartCard>

    <FunnelBreakdownPanel
      v-model:dimension="breakdownDimension"
      :data="breakdownData"
      :loading="breakdown.isPending.value"
      :error="breakdown.isError.value"
      :stale="breakdown.isFetching.value && !breakdown.isPending.value"
      @retry="breakdown.refetch()"
    />

    <ChartCard :title="t('funnels.trend.title')">
      <TimeSeriesChart
        :title="t('funnels.trend.title')"
        :categories="trendData.categories"
        :series="trendData.series"
        :loading="trend.isPending.value"
        :error="trend.isError.value"
        :stale="trend.isFetching.value && !trend.isPending.value"
        :height="280"
        @retry="trend.refetch()"
      />
    </ChartCard>
  </div>
</template>
