// =============================================================================
// Statflow Dashboard — Analytics endpoints
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { http, apiBaseUrl } from '../http'
import type {
  AggregateMetricsResponse,
  AnalyticsQueryBase,
  BreakdownQuery,
  BreakdownResponse,
  Funnel,
  FunnelCreateRequest,
  FunnelQueryRequest,
  FunnelResponse,
  Goal,
  GoalCreateRequest,
  HeatmapQueryRequest,
  HeatmapResponse,
  PublicOverviewParams,
  RealtimeResponse,
  RetentionQueryRequest,
  RetentionResponse,
  Segment,
  SegmentCreateRequest,
  TimeSeriesQuery,
  TimeSeriesResponse,
} from '../types'

export const analyticsApi = {
  aggregate: (siteId: string, query: AnalyticsQueryBase) =>
    http.post<AggregateMetricsResponse>(`/analytics/${siteId}/aggregate`, query),
  timeseries: (siteId: string, query: TimeSeriesQuery) =>
    http.post<TimeSeriesResponse>(`/analytics/${siteId}/timeseries`, query),
  breakdown: (siteId: string, query: BreakdownQuery) =>
    http.post<BreakdownResponse>(`/analytics/${siteId}/breakdown`, query),
  realtime: (siteId: string) => http.get<RealtimeResponse>(`/analytics/${siteId}/realtime`),
  queryFunnel: (siteId: string, query: FunnelQueryRequest) =>
    http.post<FunnelResponse>(`/analytics/${siteId}/funnels`, query),
  retention: (siteId: string, query: RetentionQueryRequest) =>
    http.post<RetentionResponse>(`/analytics/${siteId}/retention`, query),
  heatmap: (siteId: string, query: HeatmapQueryRequest) =>
    http.post<HeatmapResponse>(`/analytics/${siteId}/heatmap`, query),

  /**
   * URL for the realtime Server-Sent Events stream. EventSource cannot set
   * headers, so the access token rides as a query parameter (see openapi.yaml).
   */
  realtimeStreamUrl: (siteId: string, accessToken: string | null) => {
    const url = `${apiBaseUrl()}/analytics/${siteId}/realtime/stream`
    return accessToken ? `${url}?access_token=${encodeURIComponent(accessToken)}` : url
  },
}

export const funnelsApi = {
  list: (siteId: string) =>
    http.get<{ data: Funnel[] }>(`/sites/${siteId}/funnels`).then((r) => r.data),
  get: (siteId: string, funnelId: string) =>
    http.get<Funnel>(`/sites/${siteId}/funnels/${funnelId}`),
  create: (siteId: string, body: FunnelCreateRequest) =>
    http.post<Funnel>(`/sites/${siteId}/funnels`, body),
  update: (siteId: string, funnelId: string, body: FunnelCreateRequest) =>
    http.patch<Funnel>(`/sites/${siteId}/funnels/${funnelId}`, body),
  remove: (siteId: string, funnelId: string) =>
    http.delete<void>(`/sites/${siteId}/funnels/${funnelId}`),
}

export const goalsApi = {
  list: (siteId: string) =>
    http.get<{ data: Goal[] }>(`/sites/${siteId}/goals`).then((r) => r.data),
  create: (siteId: string, body: GoalCreateRequest) =>
    http.post<Goal>(`/sites/${siteId}/goals`, body),
  update: (siteId: string, goalId: string, body: GoalCreateRequest) =>
    http.patch<Goal>(`/sites/${siteId}/goals/${goalId}`, body),
  remove: (siteId: string, goalId: string) =>
    http.delete<void>(`/sites/${siteId}/goals/${goalId}`),
}

export const segmentsApi = {
  list: (siteId: string) =>
    http.get<{ data: Segment[] }>(`/analytics/${siteId}/segments`).then((r) => r.data),
  create: (siteId: string, body: SegmentCreateRequest) =>
    http.post<Segment>(`/analytics/${siteId}/segments`, body),
  remove: (siteId: string, segmentId: string) =>
    http.delete<void>(`/analytics/${siteId}/segments/${segmentId}`),
}

export const publicApi = {
  overview: (token: string, params: PublicOverviewParams = {}) => {
    const { sharePassword, ...query } = params
    return http.get<AggregateMetricsResponse>(`/public/${token}/overview`, {
      anonymous: true,
      query,
      headers: sharePassword ? { 'X-Share-Password': sharePassword } : undefined,
    })
  },
}
