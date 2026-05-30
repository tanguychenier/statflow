/**
 * Statflow Tracker — Element-visibility collector.
 *
 * Watches elements decorated with `data-track-visibility` and emits an
 * `element_visibility` event the first time each enters the viewport past the
 * configured IntersectionObserver threshold (default 0.5). `duration_ms` is the
 * time the element stayed visible, finalised on unload (null while still
 * visible). Powered entirely by IntersectionObserver — no scroll polling.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope } from '../core/envelope.js';
import { buildSelector } from '../core/dom.js';

type Push = (e: StatflowEvent) => void;

interface Tracked { trackId: string; firstSeenAt: number; ratio: number; }

export interface VisibilityCollector { mount(p: Push): void; destroy(): void; }

export function createVisibilityCollector(cfg: ResolvedConfig): VisibilityCollector {
  let push: Push | undefined;
  let observer: IntersectionObserver | undefined;
  let mo: MutationObserver | undefined;
  const seen = new Map<Element, Tracked>();
  let pageStart = Date.now();
  let onUnload: (() => void) | undefined;

  function trackIdOf(el: Element): string {
    return el.getAttribute('data-track-visibility') || buildSelector(el);
  }

  function observeAll(): void {
    if (typeof document === 'undefined' || !observer) return;
    for (const el of document.querySelectorAll('[data-track-visibility]')) {
      if (!seen.has(el)) observer.observe(el);
    }
  }

  function handle(entries: IntersectionObserverEntry[]): void {
    if (!push) return;
    for (const entry of entries) {
      const el = entry.target;
      if (!entry.isIntersecting || entry.intersectionRatio < cfg.visibilityThreshold) continue;
      if (seen.has(el)) continue;
      const trackId = trackIdOf(el);
      seen.set(el, { trackId, firstSeenAt: Date.now(), ratio: entry.intersectionRatio });
      push(buildEnvelope(cfg, {
        name: 'element_visibility',
        properties: {
          selector: buildSelector(el),
          track_id: trackId,
          visible_ratio: Math.round(entry.intersectionRatio * 100) / 100,
          time_to_ms: (Date.now() - pageStart) | 0,
          duration_ms: null,
        },
      }));
      observer!.unobserve(el);
    }
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') return;
    push = p;
    pageStart = Date.now();
    observer = new IntersectionObserver(handle, { threshold: cfg.visibilityThreshold });
    observeAll();
    if (typeof MutationObserver !== 'undefined') {
      mo = new MutationObserver(() => observeAll());
      mo.observe(document.documentElement, { childList: true, subtree: true });
    }
    onUnload = (): void => { pageStart = Date.now(); };
    window.addEventListener('sf:nav', onUnload);
  }

  function destroy(): void {
    observer?.disconnect();
    mo?.disconnect();
    if (typeof window !== 'undefined' && onUnload) window.removeEventListener('sf:nav', onUnload);
    seen.clear();
    observer = mo = push = onUnload = undefined;
  }

  return { mount, destroy };
}
