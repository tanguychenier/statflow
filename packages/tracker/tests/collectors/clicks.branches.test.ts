import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createClickCollector } from '../../src/collectors/clicks.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

function fireClick(target: Element): void {
  target.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 10, clientY: 10 }));
}

describe('clicks collector — dead-click branch coverage', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createClickCollector>;

  beforeEach(() => {
    vi.useFakeTimers();
    resetSeq();
    document.body.innerHTML = '';
    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
    collected = [];
    collector = createClickCollector(resolveConfig({ siteKey: 'stk_click_b', deadClickFenceMs: 300 }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
    vi.useRealTimers();
  });

  it('suppresses dead_click when the tab becomes hidden during the fence', async () => {
    const div = document.createElement('div');
    document.body.appendChild(div);
    fireClick(div);
    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
    await vi.advanceTimersByTimeAsync(350);
    expect(collected.find((e) => e.event === 'dead_click')).toBeUndefined();
  });

  it('cancels a pending dead-click fence on destroy()', async () => {
    const div = document.createElement('div');
    document.body.appendChild(div);
    fireClick(div);
    collector.destroy();
    await vi.advanceTimersByTimeAsync(350);
    expect(collected.find((e) => e.event === 'dead_click')).toBeUndefined();
  });

  it('skips dead-click detection when MutationObserver is unavailable', async () => {
    collector.destroy();
    vi.stubGlobal('MutationObserver', undefined);
    const c = createClickCollector(resolveConfig({ siteKey: 'stk_click_nomo', deadClickFenceMs: 300 }));
    c.mount((e) => collected.push(e));
    const div = document.createElement('div');
    document.body.appendChild(div);
    fireClick(div);
    await vi.advanceTimersByTimeAsync(350);
    expect(collected.find((e) => e.event === 'dead_click')).toBeUndefined();
    expect(collected.find((e) => e.event === 'click')).toBeDefined();
    c.destroy();
    vi.unstubAllGlobals();
  });
});
