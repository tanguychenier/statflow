import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createScrollCollector } from '../../src/collectors/scroll.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

function setLayout(pageHeight: number, viewport: number): void {
  Object.defineProperty(document.body, 'scrollHeight', { value: pageHeight, configurable: true });
  Object.defineProperty(document.documentElement, 'scrollHeight', { value: pageHeight, configurable: true });
  Object.defineProperty(window, 'innerHeight', { value: viewport, configurable: true });
}

function scrollTo(y: number): void {
  Object.defineProperty(window, 'scrollY', { value: y, configurable: true });
  window.dispatchEvent(new Event('scroll'));
}

describe('createScrollCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createScrollCollector>;

  beforeEach(() => {
    resetSeq();
    collected = [];
    scrollTo(0);
    setLayout(2000, 1000); // 1000 px of scrollable range
    collector = createScrollCollector(resolveConfig({ siteKey: 'stk_scroll', scrollThresholds: [25, 50, 75, 90, 100] }));
    collector.mount((e) => collected.push(e));
    collected = []; // ignore the initial evaluate at depth 0
  });

  afterEach(() => {
    collector.destroy();
  });

  it('fires a threshold once when crossed', () => {
    scrollTo(500); // 50%
    const fired = collected.filter((e) => e.event === 'scroll_depth').map((e) => e.behavioral?.scroll_depth_pct);
    expect(fired).toContain(25);
    expect(fired).toContain(50);
    expect(fired).not.toContain(75);
  });

  it('does not refire a threshold already crossed', () => {
    scrollTo(500);
    const countAfterFirst = collected.length;
    scrollTo(400); // scroll back up
    scrollTo(500); // and down again
    const fiftyEvents = collected.filter((e) => e.behavioral?.scroll_depth_pct === 50);
    expect(fiftyEvents).toHaveLength(1);
    expect(collected.length).toBe(countAfterFirst);
  });

  it('carries depth metadata in props and signals in behavioral', () => {
    scrollTo(750); // 75%
    const ev = collected.find((e) => e.behavioral?.scroll_depth_pct === 75)!;
    expect(ev.properties?.['depth_pct']).toBe(75);
    expect(ev.properties?.['page_height']).toBe(2000);
    expect(typeof ev.properties?.['time_to_ms']).toBe('number');
    expect(ev.behavioral?.scroll_depth_px).toBe(750);
  });

  it('reports max_pct as the deepest point reached', () => {
    scrollTo(900); // 90%
    const ev = collected.find((e) => e.behavioral?.scroll_depth_pct === 90)!;
    expect(ev.properties?.['max_pct']).toBeGreaterThanOrEqual(90);
  });

  it('treats a non-scrollable page as fully scrolled', () => {
    collector.destroy();
    collected = [];
    setLayout(500, 1000); // page shorter than the viewport
    collector = createScrollCollector(resolveConfig({ siteKey: 'stk_scroll2' }));
    collector.mount((e) => collected.push(e));
    const fired = collected.filter((e) => e.event === 'scroll_depth').map((e) => e.behavioral?.scroll_depth_pct);
    expect(fired).toContain(100);
  });

  it('reset() re-arms all thresholds', () => {
    scrollTo(1000); // 100%
    expect(collected.some((e) => e.behavioral?.scroll_depth_pct === 100)).toBe(true);
    collected = [];
    collector.reset();
    scrollTo(1000);
    expect(collected.some((e) => e.behavioral?.scroll_depth_pct === 100)).toBe(true);
  });

  it('re-arms thresholds on a sf:nav lifecycle event', () => {
    scrollTo(1000);
    collected = [];
    window.dispatchEvent(new CustomEvent('sf:nav'));
    scrollTo(1000);
    expect(collected.some((e) => e.event === 'scroll_depth')).toBe(true);
  });

  it('destroy() detaches the scroll listener', () => {
    collector.destroy();
    collected = [];
    scrollTo(1000);
    expect(collected).toHaveLength(0);
  });
});
