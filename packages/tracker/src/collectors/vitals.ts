/**
 * Statflow Tracker — Core Web Vitals collector (LCP / CLS / INP).
 *
 * Built directly on PerformanceObserver to avoid bundling the `web-vitals`
 * package (~3 KB). Each metric is reported once, when the page is backgrounded
 * or unloaded, which is when the browser has settled the final value:
 *   - LCP: the largest contentful paint candidate before the first interaction.
 *   - CLS: the maximum session-window layout-shift sum (≤ 1 s gap, ≤ 5 s window).
 *   - INP: the worst interaction latency (event-timing `duration`).
 * Thresholds follow event-taxonomy §3.11.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope } from '../core/envelope.js';
import { buildSelector } from '../core/dom.js';

type Push = (e: StatflowEvent) => void;

type Rating = 'good' | 'needs-improvement' | 'poor';

function rate(value: number, good: number, poor: number): Rating {
  if (value <= good) return 'good';
  if (value <= poor) return 'needs-improvement';
  return 'poor';
}

interface LcpEntry extends PerformanceEntry { renderTime?: number; loadTime?: number; size?: number; element?: Element | null; url?: string; }
interface LsEntry extends PerformanceEntry { value: number; hadRecentInput: boolean; sources?: Array<{ node?: Node | null }>; }
interface EvtEntry extends PerformanceEntry { interactionId?: number; target?: Element | null; }

export interface VitalsCollector { mount(p: Push): void; destroy(): void; }

function observe(type: string, opts: PerformanceObserverInit, cb: (entries: PerformanceEntry[]) => void): PerformanceObserver | undefined {
  try {
    const po = new PerformanceObserver(list => cb(list.getEntries()));
    po.observe({ type, buffered: true, ...opts } as PerformanceObserverInit);
    return po;
  } catch { return undefined; }
}

export function createVitalsCollector(cfg: ResolvedConfig): VitalsCollector {
  let push: Push | undefined;
  const observers: PerformanceObserver[] = [];
  const winListeners: Array<[string, EventListener]> = [];
  const docListeners: Array<[string, EventListener]> = [];
  let reported = false;

  let lcp: LcpEntry | undefined;
  let clsValue = 0, clsWindow = 0, clsFirstTs = 0, clsPrevTs = 0;
  const clsSources: Array<{ selector: string; fraction: number }> = [];
  let inpValue = 0; let inpEntry: EvtEntry | undefined;

  function onLcp(entries: PerformanceEntry[]): void {
    for (const e of entries) lcp = e as LcpEntry;
  }

  function onCls(entries: PerformanceEntry[]): void {
    for (const raw of entries) {
      const e = raw as LsEntry;
      if (e.hadRecentInput) continue;
      if (clsWindow !== 0 && (e.startTime - clsPrevTs > 1000 || e.startTime - clsFirstTs > 5000)) {
        clsWindow = 0; clsFirstTs = e.startTime;
      }
      if (clsWindow === 0) clsFirstTs = e.startTime;
      clsPrevTs = e.startTime;
      clsWindow += e.value;
      if (clsWindow > clsValue) {
        clsValue = clsWindow;
        clsSources.length = 0;
        const node = e.sources?.[0]?.node;
        if (node instanceof Element) clsSources.push({ selector: buildSelector(node), fraction: Math.round(e.value * 1e4) / 1e4 });
      }
    }
  }

  function onInp(entries: PerformanceEntry[]): void {
    for (const raw of entries) {
      const e = raw as EvtEntry;
      if (!e.interactionId) continue;
      if (e.duration > inpValue) { inpValue = e.duration; inpEntry = e; }
    }
  }

  function reportLcp(): void {
    if (!lcp) return;
    const value = Math.round((lcp.renderTime || lcp.loadTime || lcp.startTime) * 10) / 10;
    const props: Record<string, unknown> = { value_ms: value, rating: rate(value, 2500, 4000) };
    if (lcp.element) props['element'] = buildSelector(lcp.element);
    if (lcp.url) props['url'] = lcp.url;
    if (lcp.size) props['size'] = lcp.size;
    push!(buildEnvelope(cfg, { name: 'web_vital_lcp', properties: props }));
  }

  function reportCls(): void {
    const value = Math.round(clsValue * 1e3) / 1e3;
    push!(buildEnvelope(cfg, {
      name: 'web_vital_cls',
      properties: { value, rating: rate(value, 0.1, 0.25), sources: clsSources.slice(0, 5) },
    }));
  }

  function reportInp(): void {
    if (inpValue <= 0) return;
    const value = Math.round(inpValue);
    const props: Record<string, unknown> = { value_ms: value, rating: rate(value, 200, 500), type: inpEntry?.name };
    if (inpEntry?.target instanceof Element) props['target'] = buildSelector(inpEntry.target);
    push!(buildEnvelope(cfg, { name: 'web_vital_inp', properties: props }));
  }

  function report(): void {
    if (reported || !push) return;
    reported = true;
    reportLcp();
    reportCls();
    reportInp();
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined' || typeof PerformanceObserver === 'undefined') return;
    push = p;
    const lo = observe('largest-contentful-paint', {}, onLcp);
    const co = observe('layout-shift', {}, onCls);
    const io = observe('event', { durationThreshold: 40 } as PerformanceObserverInit, onInp);
    for (const o of [lo, co, io]) if (o) observers.push(o);
    const onVis: EventListener = () => { if (document.visibilityState === 'hidden') report(); };
    document.addEventListener('visibilitychange', onVis);
    docListeners.push(['visibilitychange', onVis]);
    const onHide: EventListener = () => report();
    window.addEventListener('pagehide', onHide);
    winListeners.push(['pagehide', onHide]);
  }

  function destroy(): void {
    for (const o of observers) o.disconnect();
    if (typeof window !== 'undefined') for (const [t, fn] of winListeners) window.removeEventListener(t, fn);
    if (typeof document !== 'undefined') for (const [t, fn] of docListeners) document.removeEventListener(t, fn);
    observers.length = 0;
    winListeners.length = 0;
    docListeners.length = 0;
    push = undefined;
  }

  return { mount, destroy };
}
