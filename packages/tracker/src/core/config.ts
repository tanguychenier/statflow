/**
 * Statflow Tracker — Configuration types and defaults.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

export type DntBehaviour = 'disable' | 'ignore';

export type CollectorName =
  | 'pageview' | 'spa' | 'clicks' | 'scroll' | 'forms'
  | 'engagement' | 'visibility' | 'vitals' | 'errors';

/** Behavioral signals (wire key `b`). See event-contract.md §5. */
export interface BehavioralSignals {
  click_x?: number;
  click_y?: number;
  click_x_pct?: number;
  click_y_pct?: number;
  element_tag?: string;
  element_text?: string;
  element_selector?: string;
  element_id?: string;
  scroll_depth_pct?: number;
  scroll_depth_px?: number;
  engagement_time_ms?: number;
  is_rage_click?: boolean;
}

export interface StatflowEvent {
  /** Client-generated UUIDv4 — wire `eid`, deduplication key. */
  eid: string;
  /** Public site key (`stk_…`) — wire `k`. */
  k: string;
  event: string;
  ts: number;
  seq: number;
  url: string;
  pathname: string;
  hostname: string;
  ref?: string;
  title?: string;
  vw: number;
  vh: number;
  sw: number;
  sh: number;
  dpr?: number;
  ct?: string;
  tv: string;
  properties?: Record<string, unknown>;
  behavioral?: BehavioralSignals;
}

export interface StatflowConfig {
  /** Public site key (`stk_…`). Required. Sent on the wire as `k`/`site_key`. */
  siteKey?: string;
  /** @deprecated Back-compat alias for {@link siteKey}. */
  projectId?: string;
  apiBase?: string;
  apiPath?: string;
  batchSize?: number;
  batchIntervalMs?: number;
  scrollThresholds?: number[];
  /** Active-engagement heartbeat interval (event-contract.md §7: 10 s). */
  engagementIntervalMs?: number;
  /** Rage-click detection window (event-contract.md §7: 1000 ms). */
  rageWindowMs?: number;
  /** Dead-click observation fence (taxonomy §3.5: 300 ms). */
  deadClickFenceMs?: number;
  /** IntersectionObserver threshold for element_visibility (taxonomy §3.9: 0.5). */
  visibilityThreshold?: number;
  disable?: CollectorName[];
  beforeSend?: (event: StatflowEvent) => StatflowEvent | null;
  sampleRate?: number;
  dnt?: DntBehaviour;
}

export interface ResolvedConfig {
  siteKey: string;
  apiBase: string;
  apiPath: string;
  batchSize: number;
  batchIntervalMs: number;
  scrollThresholds: number[];
  engagementIntervalMs: number;
  rageWindowMs: number;
  deadClickFenceMs: number;
  visibilityThreshold: number;
  disable: CollectorName[];
  beforeSend: ((event: StatflowEvent) => StatflowEvent | null) | undefined;
  sampleRate: number;
  dnt: DntBehaviour;
}

export function resolveConfig(raw: StatflowConfig): ResolvedConfig {
  return {
    siteKey: raw.siteKey ?? raw.projectId ?? '',
    apiBase: raw.apiBase ?? (typeof window !== 'undefined' ? window.location.origin : ''),
    apiPath: raw.apiPath ?? '/api/v1/events',
    batchSize: raw.batchSize ?? 20,
    batchIntervalMs: raw.batchIntervalMs ?? 5000,
    scrollThresholds: raw.scrollThresholds ?? [25, 50, 75, 90, 100],
    engagementIntervalMs: raw.engagementIntervalMs ?? 10000,
    rageWindowMs: raw.rageWindowMs ?? 1000,
    deadClickFenceMs: raw.deadClickFenceMs ?? 300,
    visibilityThreshold: raw.visibilityThreshold ?? 0.5,
    disable: raw.disable ?? [],
    beforeSend: raw.beforeSend,
    sampleRate: raw.sampleRate ?? 1,
    dnt: raw.dnt ?? 'disable',
  };
}
