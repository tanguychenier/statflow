/**
 * Statflow Tracker — Heatmap capture engine.
 *
 * Samples pointer movement, click coordinates and scroll depth into a bounded
 * ring buffer, then assembles them into a single `heatmap_batch` event
 * (event-contract.md §4; design §8.2–8.3). Coordinates are document-relative
 * pixels plus resolution-independent percentages, identical to the click
 * coordinate schema (event-contract.md §5.1) — the percentage is the stable
 * cross-resolution key, the pixel value the drill-down detail.
 *
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import { docPoint } from '../core/dom.js';
import type { ResolvedHeatmapConfig } from './config.js';
import { RingBuffer } from './ring-buffer.js';
import { rafThrottle, type Throttled } from './throttle.js';

/** Point kind — discriminates pointer trails, clicks and scroll samples. */
export type PointKind = 'm' | 'c' | 's';

/**
 * A single heatmap sample. Keys are already short because the whole `points`
 * array is serialised verbatim into the wire `props.points` field (the contract
 * stores it in `custom_properties`, event-contract.md §4 note).
 */
export interface HeatmapPoint {
  /** Document-relative X, CSS px. */
  x: number;
  /** Document-relative Y, CSS px. */
  y: number;
  /** X as a percentage of document width (event-contract.md §5.1). */
  xp: number;
  /** Y as a percentage of document height. */
  yp: number;
  /** ms since capture started — relative ordering for replay (design §8.3). */
  t: number;
  /** Sample kind: m=move, c=click, s=scroll. */
  k: PointKind;
}

export interface HeatmapBatch {
  points: HeatmapPoint[];
  duration_ms: number;
  sample_rate: number;
}

const nowMs = (): number =>
  typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : Date.now();

export interface CaptureEngine {
  start(): void;
  /** Snapshot + clear the buffer, returning a batch if any points exist. */
  collect(): HeatmapBatch | undefined;
  stop(): void;
}

export function createCaptureEngine(cfg: ResolvedHeatmapConfig): CaptureEngine {
  const buffer = new RingBuffer<HeatmapPoint>(cfg.maxPoints);
  let origin = 0;
  let lastScrollKey = -1;
  let mounted = false;

  let onMove: Throttled<[number, number]> | undefined;
  let onScroll: Throttled<[]> | undefined;
  let moveListener: ((e: MouseEvent) => void) | undefined;
  let touchListener: ((e: TouchEvent) => void) | undefined;
  let clickListener: ((e: MouseEvent) => void) | undefined;
  let scrollListener: (() => void) | undefined;

  function record(clientX: number, clientY: number, kind: PointKind): void {
    const pt = docPoint(clientX, clientY);
    buffer.push({
      x: pt.x,
      y: pt.y,
      xp: pt.xp,
      yp: pt.yp,
      t: Math.round(nowMs() - origin),
      k: kind,
    });
  }

  /** One scroll sample per discrete depth bucket avoids flooding the buffer. */
  function recordScroll(): void {
    if (typeof window === 'undefined') return;
    const sy = window.scrollY;
    const doc = typeof document !== 'undefined' ? document.body : undefined;
    const dh = doc?.scrollHeight || 1;
    const key = Math.round((sy / dh) * 100);
    if (key === lastScrollKey) return;
    lastScrollKey = key;
    // Project onto the viewport centre so scroll heat shares the point schema.
    record((window.innerWidth / 2) | 0, (window.innerHeight / 2) | 0, 's');
  }

  function shouldSample(): boolean {
    if (cfg.sampleRate >= 1) return true;
    if (cfg.sampleRate <= 0) return false;
    return Math.random() < cfg.sampleRate;
  }

  function start(): void {
    if (mounted || typeof window === 'undefined') return;
    mounted = true;
    origin = nowMs();

    onMove = rafThrottle<[number, number]>((x, y) => {
      if (shouldSample()) record(x, y, 'm');
    }, cfg.sampleIntervalMs);
    onScroll = rafThrottle<[]>(recordScroll, cfg.sampleIntervalMs);

    moveListener = (e: MouseEvent): void => onMove!(e.clientX, e.clientY);
    touchListener = (e: TouchEvent): void => {
      const t = e.touches[0];
      if (t) onMove!(t.clientX, t.clientY);
    };
    clickListener = (e: MouseEvent): void => record(e.clientX, e.clientY, 'c');
    scrollListener = (): void => onScroll!();

    window.addEventListener('mousemove', moveListener, { passive: true });
    window.addEventListener('touchmove', touchListener, { passive: true });
    window.addEventListener('click', clickListener, { capture: true, passive: true });
    window.addEventListener('scroll', scrollListener, { passive: true });
  }

  function collect(): HeatmapBatch | undefined {
    const points = buffer.drain();
    if (points.length === 0) return undefined;
    const last = points[points.length - 1]!;
    return { points, duration_ms: last.t, sample_rate: cfg.sampleRate };
  }

  function stop(): void {
    if (!mounted) return;
    mounted = false;
    onMove?.cancel();
    onScroll?.cancel();
    if (typeof window !== 'undefined') {
      if (moveListener) window.removeEventListener('mousemove', moveListener);
      if (touchListener) window.removeEventListener('touchmove', touchListener);
      if (clickListener) window.removeEventListener('click', clickListener, { capture: true });
      if (scrollListener) window.removeEventListener('scroll', scrollListener);
    }
    moveListener = touchListener = clickListener = scrollListener = undefined;
    onMove = onScroll = undefined;
    buffer.clear();
    lastScrollKey = -1;
  }

  return { start, collect, stop };
}
