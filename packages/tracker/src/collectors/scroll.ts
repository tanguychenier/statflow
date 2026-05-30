/**
 * Statflow Tracker — Scroll-depth collector.
 *
 * Each configured threshold (default 25/50/75/90/100 %) fires at most once per
 * page view. We avoid throttled scroll-event maths by computing crossings from a
 * single passive scroll listener against the live document height, which also
 * stays correct when content lazily grows. The `scroll_depth_pct` and
 * `scroll_depth_px` behavioral signals ride the wire `b` block (event-contract
 * §5); the threshold/page metadata ride `props`.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope } from '../core/envelope.js';

type Push = (e: StatflowEvent) => void;

export interface ScrollCollector { mount(p: Push): void; reset(): void; destroy(): void; }

function currentDepthPct(): { pct: number; px: number; pageHeight: number } {
  const doc = document.documentElement;
  const body = document.body;
  const px = window.scrollY | 0;
  const pageHeight = Math.max(body?.scrollHeight ?? 0, doc?.scrollHeight ?? 0, doc?.clientHeight ?? 0);
  const viewport = window.innerHeight || doc?.clientHeight || 0;
  const scrollable = Math.max(pageHeight - viewport, 0);
  const pct = scrollable === 0 ? 100 : Math.min(100, Math.round(((px) / scrollable) * 100));
  return { pct, px, pageHeight };
}

export function createScrollCollector(cfg: ResolvedConfig): ScrollCollector {
  const thresholds = [...cfg.scrollThresholds].sort((a, b) => a - b);
  let fired = new Set<number>();
  let maxPct = 0;
  let startedAt = Date.now();
  let push: Push | undefined;
  let handler: (() => void) | undefined;
  let navListener: (() => void) | undefined;

  function evaluate(): void {
    if (!push) return;
    const { pct, px, pageHeight } = currentDepthPct();
    if (pct > maxPct) maxPct = pct;
    for (const th of thresholds) {
      if (pct >= th && !fired.has(th)) {
        fired.add(th);
        push(buildEnvelope(cfg, {
          name: 'scroll_depth',
          properties: { depth_pct: th, max_pct: maxPct, page_height: pageHeight, time_to_ms: (Date.now() - startedAt) | 0 },
          behavioral: { scroll_depth_pct: th, scroll_depth_px: px },
        }));
      }
    }
  }

  function reset(): void {
    fired = new Set<number>();
    maxPct = 0;
    startedAt = Date.now();
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined') return;
    push = p;
    handler = (): void => evaluate();
    window.addEventListener('scroll', handler, { passive: true });
    navListener = (): void => reset();
    window.addEventListener('sf:nav', navListener);
    evaluate();
  }

  function destroy(): void {
    if (typeof window !== 'undefined') {
      if (handler) window.removeEventListener('scroll', handler);
      if (navListener) window.removeEventListener('sf:nav', navListener);
    }
    handler = navListener = push = undefined;
  }

  return { mount, reset, destroy };
}
