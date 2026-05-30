import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createVitalsCollector } from '../../src/collectors/vitals.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

interface MockObserverState { type: string; emit(entries: unknown[]): void; }

class MockPO {
  static observers: MockObserverState[] = [];
  cb: PerformanceObserverCallback;
  constructor(cb: PerformanceObserverCallback) { this.cb = cb; }
  observe(opts: { type: string }): void {
    MockPO.observers.push({
      type: opts.type,
      emit: (entries: unknown[]) =>
        this.cb({ getEntries: () => entries as PerformanceEntryList } as PerformanceObserverEntryList, this as unknown as PerformanceObserver),
    });
  }
  disconnect(): void {}
  takeRecords(): PerformanceEntryList { return []; }
}

function emit(type: string, entries: unknown[]): void {
  for (const o of MockPO.observers) if (o.type === type) o.emit(entries);
}

function setVisibility(state: 'visible' | 'hidden'): void {
  Object.defineProperty(document, 'visibilityState', { value: state, configurable: true });
}

describe('createVitalsCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createVitalsCollector>;

  beforeEach(() => {
    resetSeq();
    MockPO.observers = [];
    setVisibility('visible');
    vi.stubGlobal('PerformanceObserver', MockPO as unknown as typeof PerformanceObserver);
    collected = [];
    collector = createVitalsCollector(resolveConfig({ siteKey: 'stk_vitals' }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
    vi.unstubAllGlobals();
  });

  function report(): void {
    setVisibility('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
  }

  it('reports LCP with a value and rating on hide', () => {
    const lcpEl = document.createElement('img');
    lcpEl.className = 'hero';
    document.body.appendChild(lcpEl);
    emit('largest-contentful-paint', [{ renderTime: 1840.5, size: 1000, element: lcpEl, url: 'https://cdn/x.webp' }]);
    report();

    const lcp = collected.find((e) => e.event === 'web_vital_lcp')!;
    expect(lcp).toBeDefined();
    expect(lcp.properties?.['value_ms']).toBe(1840.5);
    expect(lcp.properties?.['rating']).toBe('good');
    expect(lcp.properties?.['element']).toContain('img');
    expect(lcp.properties?.['url']).toBe('https://cdn/x.webp');
    expect(lcp.properties?.['size']).toBe(1000);
  });

  it('rates a slow LCP as poor', () => {
    emit('largest-contentful-paint', [{ renderTime: 5000 }]);
    report();
    expect(collected.find((e) => e.event === 'web_vital_lcp')!.properties?.['rating']).toBe('poor');
  });

  it('accumulates CLS within the session window and reports the max', () => {
    emit('layout-shift', [{ value: 0.05, hadRecentInput: false, startTime: 100 }]);
    emit('layout-shift', [{ value: 0.03, hadRecentInput: false, startTime: 200 }]);
    emit('layout-shift', [{ value: 0.5, hadRecentInput: true, startTime: 300 }]); // excluded
    report();

    const cls = collected.find((e) => e.event === 'web_vital_cls')!;
    expect(cls.properties?.['value']).toBeCloseTo(0.08, 3);
    expect(cls.properties?.['rating']).toBe('good');
  });

  it('starts a fresh CLS window after a gap larger than 1 s', () => {
    emit('layout-shift', [{ value: 0.2, hadRecentInput: false, startTime: 0 }]);
    emit('layout-shift', [{ value: 0.05, hadRecentInput: false, startTime: 2000 }]); // new window
    report();
    const cls = collected.find((e) => e.event === 'web_vital_cls')!;
    expect(cls.properties?.['value']).toBeCloseTo(0.2, 3);
  });

  it('reports the worst INP interaction', () => {
    const btn = document.createElement('button');
    btn.className = 'add';
    document.body.appendChild(btn);
    emit('event', [{ duration: 60, interactionId: 1, name: 'pointerdown', target: btn }]);
    emit('event', [{ duration: 220, interactionId: 2, name: 'click', target: btn }]);
    emit('event', [{ duration: 999, interactionId: 0, name: 'noise' }]); // no interactionId → ignored
    report();

    const inp = collected.find((e) => e.event === 'web_vital_inp')!;
    expect(inp.properties?.['value_ms']).toBe(220);
    expect(inp.properties?.['rating']).toBe('needs-improvement');
    expect(inp.properties?.['type']).toBe('click');
    expect(inp.properties?.['target']).toContain('button');
  });

  it('reports each metric at most once', () => {
    emit('largest-contentful-paint', [{ renderTime: 1000 }]);
    report();
    report();
    window.dispatchEvent(new Event('pagehide'));
    expect(collected.filter((e) => e.event === 'web_vital_lcp')).toHaveLength(1);
  });

  it('omits LCP and INP when no data was observed but still reports CLS as 0', () => {
    report();
    expect(collected.find((e) => e.event === 'web_vital_lcp')).toBeUndefined();
    expect(collected.find((e) => e.event === 'web_vital_inp')).toBeUndefined();
    const cls = collected.find((e) => e.event === 'web_vital_cls')!;
    expect(cls.properties?.['value']).toBe(0);
  });

  it('is a no-op when PerformanceObserver is unavailable', () => {
    vi.stubGlobal('PerformanceObserver', undefined);
    const c = createVitalsCollector(resolveConfig({ siteKey: 'stk_vitals_none' }));
    expect(() => c.mount((e) => collected.push(e))).not.toThrow();
    c.destroy();
  });
});
