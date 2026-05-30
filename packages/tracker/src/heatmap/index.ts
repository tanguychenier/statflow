/**
 * Statflow Tracker — Heatmap module (lazily-loaded entry point).
 *
 * This is a SEPARATE tsup bundle (`tracker-heatmap.js`). It is never imported
 * by the core `src/index.ts`; the core stays under its 2 KB budget and the
 * product loads this module on demand via `import()` (design §7, §8). The
 * module captures throttled pointer trails, click coordinates and scroll depth,
 * and flushes them as a single `heatmap_batch` event in the compact wire format
 * (event-contract.md §3–§4) through the same transport as the core tracker.
 *
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import { buildEnvelope } from '../core/envelope.js';
import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { sendBatch } from '../core/transport.js';
import { evaluatePrivacy, evaluateSampleRate } from '../collectors/privacy.js';
import { resolveHeatmapConfig, type HeatmapOptions, type ResolvedHeatmapConfig } from './config.js';
import { createCaptureEngine, type CaptureEngine, type HeatmapBatch } from './capture.js';

export type { HeatmapOptions, HeatmapBatch };
export type { HeatmapPoint } from './capture.js';

export interface HeatmapModule {
  /** Flush the accumulated points immediately as a `heatmap_batch`. */
  flush(): Promise<void>;
  /** Stop capture, detach listeners, and flush whatever remains. */
  destroy(): Promise<void>;
}

/**
 * `buildEnvelope` only reads `siteKey` from the config; the heatmap module has
 * no `beforeSend`/queue, so a minimal shape keeps the bundle decoupled from the
 * full core `ResolvedConfig` while remaining structurally compatible.
 */
const envelopeConfig = (cfg: ResolvedHeatmapConfig): Pick<ResolvedConfig, 'siteKey'> => ({
  siteKey: cfg.siteKey,
});

function buildBatchEvent(cfg: ResolvedHeatmapConfig, batch: HeatmapBatch): StatflowEvent {
  return buildEnvelope(envelopeConfig(cfg) as ResolvedConfig, {
    name: 'heatmap_batch',
    properties: {
      points: batch.points,
      duration_ms: batch.duration_ms,
      sample_rate: batch.sample_rate,
    },
  });
}

const swallow = async (p: Promise<void>): Promise<void> => {
  try {
    await p;
  } catch {
    /* the tracker is silent in production (design §11) */
  }
};

/**
 * Initialise heatmap capture. Returns a no-op handle when a privacy signal or
 * the session sample roll opts the visitor out — heatmaps are opt-in and honour
 * the same DNT/GPC rules as the core tracker (design §8.4).
 */
export function init(opts: HeatmapOptions = {}): HeatmapModule {
  const cfg = resolveHeatmapConfig(opts);

  const optedOut =
    typeof window === 'undefined' ||
    evaluatePrivacy(cfg.dnt) === 'halt' ||
    !evaluateSampleRate(cfg.sampleRate);
  if (optedOut) {
    return { flush: () => Promise.resolve(), destroy: () => Promise.resolve() };
  }

  const engine: CaptureEngine = createCaptureEngine(cfg);
  const endpoint = `${cfg.apiBase}${cfg.apiPath}`;
  let timer: ReturnType<typeof setInterval> | undefined;
  let hideListener: (() => void) | undefined;

  async function flush(): Promise<void> {
    const batch = engine.collect();
    if (!batch) return;
    await sendBatch([buildBatchEvent(cfg, batch)], endpoint);
  }

  engine.start();
  timer = setInterval(() => void swallow(flush()), cfg.flushIntervalMs);

  if (typeof window !== 'undefined') {
    hideListener = (): void => {
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        void swallow(flush());
      }
    };
    window.addEventListener('visibilitychange', hideListener);
    window.addEventListener('pagehide', hideListener);
  }

  async function destroy(): Promise<void> {
    if (timer !== undefined) {
      clearInterval(timer);
      timer = undefined;
    }
    if (hideListener && typeof window !== 'undefined') {
      window.removeEventListener('visibilitychange', hideListener);
      window.removeEventListener('pagehide', hideListener);
      hideListener = undefined;
    }
    await swallow(flush());
    engine.stop();
  }

  return { flush, destroy };
}
