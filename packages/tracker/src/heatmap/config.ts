/**
 * Statflow Tracker — Heatmap module configuration.
 *
 * The heatmap module is a separately built, lazily-loaded bundle
 * (`tracker-heatmap.js`). It carries its own minimal configuration surface so
 * it never has to import the core `ResolvedConfig`; the only values it shares
 * with the core are the site key and the ingestion endpoint, which the caller
 * forwards when it loads the module.
 *
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { DntBehaviour } from '../core/config.js';

export interface HeatmapOptions {
  /** Public site key (`stk_…`) — wire `k`. Required to attribute the batch. */
  siteKey?: string;
  /** Back-compat alias for {@link siteKey}. */
  projectId?: string;
  /** Ingestion base URL. Defaults to the page origin (first-party proxy). */
  apiBase?: string;
  /** Path under apiBase for the ingestion endpoint. */
  apiPath?: string;
  /**
   * Mousemove sampling period in ms. A pointer position is retained at most
   * once per window; intermediate moves are discarded (design §8.2).
   */
  sampleIntervalMs?: number;
  /** Fraction of sampling windows retained, 0–1. Recorded on the batch. */
  sampleRate?: number;
  /** Ring-buffer capacity. Oldest points are overwritten past this (design §8.2). */
  maxPoints?: number;
  /** Flush cadence for the accumulated point buffer. */
  flushIntervalMs?: number;
  /** DNT / GPC handling, mirrored from the core tracker. */
  dnt?: DntBehaviour;
}

export interface ResolvedHeatmapConfig {
  siteKey: string;
  apiBase: string;
  apiPath: string;
  sampleIntervalMs: number;
  sampleRate: number;
  maxPoints: number;
  flushIntervalMs: number;
  dnt: DntBehaviour;
}

const origin = (): string => (typeof window !== 'undefined' ? window.location.origin : '');

export function resolveHeatmapConfig(raw: HeatmapOptions): ResolvedHeatmapConfig {
  return {
    siteKey: raw.siteKey ?? raw.projectId ?? '',
    apiBase: raw.apiBase ?? origin(),
    apiPath: raw.apiPath ?? '/api/v1/events',
    sampleIntervalMs: raw.sampleIntervalMs ?? 50,
    sampleRate: clampRate(raw.sampleRate),
    maxPoints: raw.maxPoints ?? 2000,
    flushIntervalMs: raw.flushIntervalMs ?? 5000,
    dnt: raw.dnt ?? 'disable',
  };
}

/** Sample rate defaults to fully on and is clamped to the unit interval. */
function clampRate(rate: number | undefined): number {
  if (rate === undefined) return 1;
  if (rate < 0) return 0;
  if (rate > 1) return 1;
  return rate;
}
