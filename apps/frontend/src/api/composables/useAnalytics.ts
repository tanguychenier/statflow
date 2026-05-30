// =============================================================================
// Statflow Dashboard — Analytics query composables
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Thin TanStack Query wrappers around the analytics endpoints. Every composable
// accepts reactive refs for the site id and query so charts react to global
// date-range / site changes without manual refetching. Queries stay disabled
// until a site id is present (avoids firing before a site is selected).
// =============================================================================

import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { analyticsApi } from '../endpoints/analytics'
import { queryKeys } from '../queryKeys'
import type {
  AnalyticsQueryBase,
  BreakdownQuery,
  FunnelQueryRequest,
  HeatmapQueryRequest,
  RetentionQueryRequest,
  TimeSeriesQuery,
} from '../types'

const DEFAULT_STALE_TIME = 60_000
const DEFAULT_GC_TIME = 300_000

export function useAggregateMetrics(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<AnalyticsQueryBase>,
) {
  return useQuery({
    queryKey: computed(() => queryKeys.aggregate(toValue(siteId) ?? '', toValue(query))),
    queryFn: () => analyticsApi.aggregate(toValue(siteId) as string, toValue(query)),
    enabled: computed(() => Boolean(toValue(siteId))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}

export function useTimeSeries(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<TimeSeriesQuery>,
) {
  return useQuery({
    queryKey: computed(() => queryKeys.timeseries(toValue(siteId) ?? '', toValue(query))),
    queryFn: () => analyticsApi.timeseries(toValue(siteId) as string, toValue(query)),
    enabled: computed(() => Boolean(toValue(siteId))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}

export function useBreakdown(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<BreakdownQuery>,
) {
  return useQuery({
    queryKey: computed(() => queryKeys.breakdown(toValue(siteId) ?? '', toValue(query))),
    queryFn: () => analyticsApi.breakdown(toValue(siteId) as string, toValue(query)),
    enabled: computed(() => Boolean(toValue(siteId))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}

export function useRealtime(
  siteId: MaybeRefOrGetter<string | null>,
  options: { refetchInterval?: number } = {},
) {
  return useQuery({
    queryKey: computed(() => queryKeys.realtime(toValue(siteId) ?? '')),
    queryFn: () => analyticsApi.realtime(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
    refetchInterval: options.refetchInterval ?? 5_000,
    staleTime: 0,
  })
}

export function useFunnelQuery(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<FunnelQueryRequest | null>,
) {
  return useQuery({
    queryKey: computed(() =>
      queryKeys.funnelQuery(toValue(siteId) ?? '', toValue(query) as FunnelQueryRequest),
    ),
    queryFn: () => analyticsApi.queryFunnel(toValue(siteId) as string, toValue(query) as FunnelQueryRequest),
    enabled: computed(() => Boolean(toValue(siteId)) && Boolean(toValue(query))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}

export function useRetention(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<RetentionQueryRequest>,
) {
  return useQuery({
    queryKey: computed(() => queryKeys.retention(toValue(siteId) ?? '', toValue(query))),
    queryFn: () => analyticsApi.retention(toValue(siteId) as string, toValue(query)),
    enabled: computed(() => Boolean(toValue(siteId))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}

export function useHeatmap(
  siteId: MaybeRefOrGetter<string | null>,
  query: MaybeRefOrGetter<HeatmapQueryRequest | null>,
) {
  return useQuery({
    queryKey: computed(() =>
      queryKeys.heatmap(toValue(siteId) ?? '', toValue(query) as HeatmapQueryRequest),
    ),
    queryFn: () => analyticsApi.heatmap(toValue(siteId) as string, toValue(query) as HeatmapQueryRequest),
    enabled: computed(() => Boolean(toValue(siteId)) && Boolean(toValue(query))),
    staleTime: DEFAULT_STALE_TIME,
    gcTime: DEFAULT_GC_TIME,
  })
}
