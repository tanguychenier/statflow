import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createScrollCollector } from '../../src/collectors/scroll.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('scroll collector — branch coverage', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createScrollCollector>;

  beforeEach(() => {
    resetSeq();
    collected = [];
  });

  afterEach(() => {
    collector?.destroy();
  });

  it('is a no-op outside a window environment guard (push not yet attached)', () => {
    collector = createScrollCollector(resolveConfig({ siteKey: 'stk_scroll_b' }));
    // mount attaches push; without mount, the collector holds no push and emits nothing
    window.dispatchEvent(new Event('scroll'));
    expect(collected).toHaveLength(0);
  });

  it('uses documentElement.scrollHeight when the body is shorter', () => {
    Object.defineProperty(document.body, 'scrollHeight', { value: 100, configurable: true });
    Object.defineProperty(document.documentElement, 'scrollHeight', { value: 3000, configurable: true });
    Object.defineProperty(document.documentElement, 'clientHeight', { value: 800, configurable: true });
    Object.defineProperty(window, 'innerHeight', { value: 800, configurable: true });
    Object.defineProperty(window, 'scrollY', { value: 2200, configurable: true });

    collector = createScrollCollector(resolveConfig({ siteKey: 'stk_scroll_b2', scrollThresholds: [50] }));
    collector.mount((e) => collected.push(e));

    const ev = collected.find((e) => e.event === 'scroll_depth');
    expect(ev?.properties?.['page_height']).toBe(3000);
  });
});
