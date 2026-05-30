// =============================================================================
// Statflow Dashboard — TanStack Query key registry
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A single source of truth for cache keys. Centralising them keeps
// invalidation predictable: invalidating `queryKeys.analytics(siteId)` drops
// every aggregate/timeseries/breakdown query for that site in one call.
// =============================================================================

import type {
  AnalyticsQueryBase,
  BreakdownQuery,
  FunnelQueryRequest,
  HeatmapQueryRequest,
  ListSitesParams,
  RetentionQueryRequest,
  TimeSeriesQuery,
} from './types'

export const queryKeys = {
  currentUser: () => ['users', 'me'] as const,

  teams: () => ['teams'] as const,
  team: (teamId: string) => ['teams', teamId] as const,
  teamMembers: (teamId: string) => ['teams', teamId, 'members'] as const,

  apiKeys: () => ['api-keys'] as const,

  // Site *list* queries are keyed under a dedicated `list` segment so they can be
  // invalidated on their own. Invalidating the bare `['sites']` prefix would also
  // drop every site-scoped detail query (settings, goals, funnels, segments,
  // reports, …), which all live under `['sites', siteId, …]`.
  sitesList: () => ['sites', 'list'] as const,
  sites: (params?: ListSitesParams) => ['sites', 'list', params ?? {}] as const,
  site: (siteId: string) => ['sites', siteId] as const,
  siteSettings: (siteId: string) => ['sites', siteId, 'settings'] as const,

  goals: (siteId: string) => ['sites', siteId, 'goals'] as const,
  funnels: (siteId: string) => ['sites', siteId, 'funnels'] as const,
  funnel: (siteId: string, funnelId: string) => ['sites', siteId, 'funnels', funnelId] as const,
  segments: (siteId: string) => ['sites', siteId, 'segments'] as const,

  savedReports: (siteId: string) => ['sites', siteId, 'reports'] as const,
  scheduledReports: (siteId: string) => ['sites', siteId, 'scheduled-reports'] as const,
  alerts: (siteId: string) => ['sites', siteId, 'alerts'] as const,

  // Analytics — the `analytics` prefix groups everything date-range dependent
  // so a global date-range change can invalidate it wholesale.
  analytics: (siteId: string) => ['analytics', siteId] as const,
  aggregate: (siteId: string, query: AnalyticsQueryBase) =>
    ['analytics', siteId, 'aggregate', query] as const,
  timeseries: (siteId: string, query: TimeSeriesQuery) =>
    ['analytics', siteId, 'timeseries', query] as const,
  breakdown: (siteId: string, query: BreakdownQuery) =>
    ['analytics', siteId, 'breakdown', query] as const,
  realtime: (siteId: string) => ['analytics', siteId, 'realtime'] as const,
  funnelQuery: (siteId: string, query: FunnelQueryRequest) =>
    ['analytics', siteId, 'funnel-query', query] as const,
  retention: (siteId: string, query: RetentionQueryRequest) =>
    ['analytics', siteId, 'retention', query] as const,
  heatmap: (siteId: string, query: HeatmapQueryRequest) =>
    ['analytics', siteId, 'heatmap', query] as const,

  publicOverview: (token: string, from?: string, to?: string) =>
    ['public', token, 'overview', from, to] as const,
} as const
