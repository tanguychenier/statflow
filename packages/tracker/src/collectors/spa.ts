/**
 * Statflow Tracker — SPA route-change collector.
 *
 * Patches the History API and listens for popstate/hashchange to surface soft
 * navigations. After emitting a `route_change` it broadcasts a `sf:nav` window
 * event so per-page-view collectors (scroll, forms, engagement, visibility) can
 * reset their state without coupling to this module.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { BROWSER } from '../core/env.js';
import { buildEnvelope, sanitiseUrl } from '../core/envelope.js';

export type RouteChangeHandler = (event: StatflowEvent) => void;

export const NAV_EVENT = 'sf:nav';

export interface SpaCollector {
  mount(onRouteChange: RouteChangeHandler): void;
  destroy(): void;
}

type PushArgs = Parameters<typeof history.pushState>;

export function createSpaCollector(config: ResolvedConfig): SpaCollector {
  let prevUrl = BROWSER ? location.href : '';
  let prevPath = BROWSER ? location.pathname + location.hash : '';
  let origPush: typeof history.pushState | undefined;
  let origReplace: typeof history.replaceState | undefined;
  let rcListener: ((e: Event) => void) | undefined;
  let popListener: (() => void) | undefined;
  let hashListener: (() => void) | undefined;

  function emit(method: string, prev: string): void {
    window.dispatchEvent(new CustomEvent('sf:rc', { detail: { method, prevUrl: prev, at: Date.now() } }));
  }

  function paintProxy(start: number, done: (ms: number) => void): void {
    if (typeof requestAnimationFrame === 'function') requestAnimationFrame(() => done((Date.now() - start) | 0));
    else done(0);
  }

  function mount(onRouteChange: RouteChangeHandler): void {
    if (!BROWSER) return;

    origPush = history.pushState.bind(history);
    const _push = origPush;
    history.pushState = (...a: PushArgs): void => {
      const p = prevUrl; _push(...a); emit('pushState', p);
    };

    origReplace = history.replaceState.bind(history);
    const _replace = origReplace;
    history.replaceState = (...a: PushArgs): void => {
      const cur = location.pathname + location.hash;
      const p = prevUrl; _replace(...a);
      if (location.pathname + location.hash !== cur) emit('replaceState', p);
    };

    rcListener = (e: Event): void => {
      const d = (e as CustomEvent<{ method: string; prevUrl: string; at: number }>).detail;
      prevPath = location.pathname + location.hash;
      paintProxy(d.at, (duration_ms) => {
        const ev = buildEnvelope(config, {
          name: 'route_change',
          properties: { prev_url: sanitiseUrl(d.prevUrl), method: d.method, duration_ms },
        });
        prevUrl = location.href;
        onRouteChange(ev);
        window.dispatchEvent(new CustomEvent(NAV_EVENT));
      });
    };
    window.addEventListener('sf:rc', rcListener);

    popListener = (): void => { const p = prevUrl; emit('popstate', p); };
    window.addEventListener('popstate', popListener);

    hashListener = (): void => {
      const cur = location.pathname + location.hash;
      if (cur !== prevPath) { const p = prevUrl; emit('hashchange', p); }
    };
    window.addEventListener('hashchange', hashListener);
  }

  function destroy(): void {
    if (!BROWSER) return;
    if (origPush) { history.pushState = origPush; origPush = undefined; }
    if (origReplace) { history.replaceState = origReplace; origReplace = undefined; }
    if (rcListener) { window.removeEventListener('sf:rc', rcListener); rcListener = undefined; }
    if (popListener) { window.removeEventListener('popstate', popListener); popListener = undefined; }
    if (hashListener) { window.removeEventListener('hashchange', hashListener); hashListener = undefined; }
  }

  return { mount, destroy };
}
