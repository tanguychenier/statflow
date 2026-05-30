/**
 * Statflow Tracker — Active-engagement collector.
 *
 * "Active" = the tab is visible AND a qualifying interaction (mousemove,
 * keydown, scroll, click) happened within the heartbeat window. Active time is
 * accumulated and a heartbeat `engagement` event is emitted every interval and
 * once on page unload. `engagement_time_ms` (active ms since the last heartbeat)
 * rides the wire `b` block; cumulative/wall-clock totals ride `props`.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope } from '../core/envelope.js';

type Push = (e: StatflowEvent) => void;

const QUALIFYING = ['mousemove', 'keydown', 'scroll', 'click'] as const;

export interface EngagementCollector { mount(p: Push): void; reset(): void; destroy(): void; }

export function createEngagementCollector(cfg: ResolvedConfig): EngagementCollector {
  const interval = cfg.engagementIntervalMs;
  let push: Push | undefined;
  let timer: ReturnType<typeof setInterval> | undefined;
  let pageStart = Date.now();
  let lastTick = Date.now();
  let lastInteraction = 0;
  let activeAcc = 0;        // active ms not yet flushed in a heartbeat
  let totalActive = 0;      // cumulative active ms since page view
  let intervals = 0;

  const winListeners: Array<[string, EventListener]> = [];
  const docListeners: Array<[string, EventListener]> = [];

  const markActive = (): void => { lastInteraction = Date.now(); };

  function isVisible(): boolean {
    return typeof document === 'undefined' || document.visibilityState !== 'hidden';
  }

  function accrue(now: number): void {
    const delta = now - lastTick;
    lastTick = now;
    if (delta <= 0) return;
    if (isVisible() && now - lastInteraction <= interval) {
      activeAcc += delta;
      totalActive += delta;
    }
  }

  function emit(onUnload: boolean): void {
    if (!push) return;
    accrue(Date.now());
    if (activeAcc <= 0 && !onUnload) return;
    intervals++;
    push(buildEnvelope(cfg, {
      name: 'engagement',
      properties: { active_ms: totalActive | 0, total_ms: (Date.now() - pageStart) | 0, intervals, on_unload: onUnload },
      behavioral: { engagement_time_ms: activeAcc | 0 },
    }));
    activeAcc = 0;
  }

  function reset(): void {
    pageStart = Date.now();
    lastTick = Date.now();
    activeAcc = 0;
    totalActive = 0;
    intervals = 0;
  }

  function onWin(type: string, fn: EventListener): void {
    window.addEventListener(type, fn, { passive: true });
    winListeners.push([type, fn]);
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined') return;
    push = p;
    reset();
    for (const ev of QUALIFYING) onWin(ev, markActive);
    onWin('pagehide', () => emit(true));
    onWin('sf:nav', () => { emit(true); reset(); });
    const onVis: EventListener = () => { if (document.visibilityState === 'hidden') emit(true); };
    document.addEventListener('visibilitychange', onVis);
    docListeners.push(['visibilitychange', onVis]);
    timer = setInterval(() => emit(false), interval);
  }

  function destroy(): void {
    if (timer != null) { clearInterval(timer); timer = undefined; }
    if (typeof window !== 'undefined') for (const [t, fn] of winListeners) window.removeEventListener(t, fn);
    if (typeof document !== 'undefined') for (const [t, fn] of docListeners) document.removeEventListener(t, fn);
    winListeners.length = 0;
    docListeners.length = 0;
    push = undefined;
  }

  return { mount, reset, destroy };
}
