// =============================================================================
// Realtime — screen component barrel (screens.md §4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

export { default as RealtimeKpiCard } from './RealtimeKpiCard.vue'
export { default as RealtimeTrendChart } from './RealtimeTrendChart.vue'
export { default as RealtimeBreakdownList } from './RealtimeBreakdownList.vue'
export { default as LiveEventStream } from './LiveEventStream.vue'
export { default as RealtimeStatusPill } from './RealtimeStatusPill.vue'

export { useRealtimeStream } from './useRealtimeStream'
export type {
  RealtimeStreamStatus,
  RealtimeEventSource,
  EventSourceFactory,
  UseRealtimeStream,
  UseRealtimeStreamOptions,
} from './useRealtimeStream'

export {
  TREND_BUCKET_COUNT,
  MAX_EVENTS_DESKTOP,
  MAX_EVENTS_MOBILE,
  appendEvent,
  rankRows,
  topPagesRows,
  topSourcesRows,
  topReferrers,
  topCountries,
  mobileSharePct,
  eventsPerMinute,
  eventsInWindow,
  topPageLabel,
  buildTrend,
  eventTone,
  eventClock,
  environmentLabel,
} from './realtimeModel'
export type { RankedRow, TrendBucket, LiveEventRow, EventTone } from './realtimeModel'

export { buildRealtimeTrendOption } from './realtimeChart'
