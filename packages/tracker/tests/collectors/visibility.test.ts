import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createVisibilityCollector } from '../../src/collectors/visibility.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

/** Controllable IntersectionObserver mock that records observed elements. */
class MockIO {
  static instances: MockIO[] = [];
  observed = new Set<Element>();
  unobserved = new Set<Element>();
  cb: IntersectionObserverCallback;
  constructor(cb: IntersectionObserverCallback) {
    this.cb = cb;
    MockIO.instances.push(this);
  }
  observe(el: Element): void { this.observed.add(el); }
  unobserve(el: Element): void { this.unobserved.add(el); this.observed.delete(el); }
  disconnect(): void { this.observed.clear(); }
  takeRecords(): IntersectionObserverEntry[] { return []; }
  trigger(el: Element, ratio: number): void {
    this.cb([{ target: el, isIntersecting: ratio > 0, intersectionRatio: ratio } as unknown as IntersectionObserverEntry], this as unknown as IntersectionObserver);
  }
}

describe('createVisibilityCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createVisibilityCollector>;

  beforeEach(() => {
    resetSeq();
    document.body.innerHTML = '';
    MockIO.instances = [];
    vi.stubGlobal('IntersectionObserver', MockIO as unknown as typeof IntersectionObserver);
    collected = [];
    collector = createVisibilityCollector(resolveConfig({ siteKey: 'stk_vis', visibilityThreshold: 0.5 }));
  });

  afterEach(() => {
    collector.destroy();
    vi.unstubAllGlobals();
  });

  function el(trackId: string): HTMLElement {
    const e = document.createElement('section');
    e.setAttribute('data-track-visibility', trackId);
    document.body.appendChild(e);
    return e;
  }

  it('observes decorated elements on mount', () => {
    const a = el('pricing');
    collector.mount((e) => collected.push(e));
    expect(MockIO.instances[0]!.observed.has(a)).toBe(true);
  });

  it('emits element_visibility when an element passes the threshold', () => {
    const a = el('pricing');
    collector.mount((e) => collected.push(e));
    MockIO.instances[0]!.trigger(a, 0.82);

    const ev = collected.find((e) => e.event === 'element_visibility')!;
    expect(ev).toBeDefined();
    expect(ev.properties?.['track_id']).toBe('pricing');
    expect(ev.properties?.['visible_ratio']).toBe(0.82);
    expect(typeof ev.properties?.['time_to_ms']).toBe('number');
    expect(ev.properties?.['duration_ms']).toBeNull();
  });

  it('does not emit below the threshold', () => {
    const a = el('pricing');
    collector.mount((e) => collected.push(e));
    MockIO.instances[0]!.trigger(a, 0.3);
    expect(collected.find((e) => e.event === 'element_visibility')).toBeUndefined();
  });

  it('emits only once per element', () => {
    const a = el('pricing');
    collector.mount((e) => collected.push(e));
    MockIO.instances[0]!.trigger(a, 0.6);
    MockIO.instances[0]!.trigger(a, 0.9);
    expect(collected.filter((e) => e.event === 'element_visibility')).toHaveLength(1);
  });

  it('is a no-op when IntersectionObserver is unavailable', () => {
    vi.stubGlobal('IntersectionObserver', undefined);
    const c = createVisibilityCollector(resolveConfig({ siteKey: 'stk_vis_none' }));
    expect(() => c.mount((e) => collected.push(e))).not.toThrow();
    c.destroy();
  });

  it('destroy() disconnects the observer', () => {
    el('pricing');
    collector.mount((e) => collected.push(e));
    const io = MockIO.instances[0]!;
    collector.destroy();
    expect(io.observed.size).toBe(0);
  });
});
