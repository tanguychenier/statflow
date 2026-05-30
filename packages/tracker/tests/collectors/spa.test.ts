import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createSpaCollector, NAV_EVENT } from '../../src/collectors/spa.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

const config = resolveConfig({ siteKey: 'stk_spa_test' });

/** route_change is emitted on the next animation frame (paint proxy). */
function nextFrame(): Promise<void> {
  return new Promise((resolve) => {
    requestAnimationFrame(() => setTimeout(resolve, 0));
  });
}

describe('createSpaCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createSpaCollector>;

  beforeEach(async () => {
    resetSeq();
    history.replaceState({}, '', '/');
    await nextFrame(); // drain any animation frame queued by a previous test
    collected = [];
    collector = createSpaCollector(config);
    collector.mount((event) => collected.push(event));
  });

  afterEach(() => {
    collector.destroy();
    vi.restoreAllMocks();
  });

  it('emits route_change on history.pushState', async () => {
    history.pushState({}, '', '/new-page');
    await nextFrame();
    expect(collected).toHaveLength(1);
    expect(collected[0]!.event).toBe('route_change');
    expect(collected[0]!.properties?.['method']).toBe('pushState');
    expect(typeof collected[0]!.properties?.['duration_ms']).toBe('number');
    history.pushState({}, '', '/');
  });

  it('emits route_change on popstate', async () => {
    history.pushState({}, '', '/page-a');
    await nextFrame();
    collected = [];

    window.dispatchEvent(new PopStateEvent('popstate', {}));
    await nextFrame();
    expect(collected).toHaveLength(1);
    expect(collected[0]!.event).toBe('route_change');
    expect(collected[0]!.properties?.['method']).toBe('popstate');

    history.pushState({}, '', '/');
  });

  it('emits route_change on hashchange when path changes', async () => {
    history.pushState({}, '', '/page#section-1');
    await nextFrame();
    collected = [];

    history.pushState({}, '', '/page#section-2');
    await nextFrame();
    expect(collected.length).toBeGreaterThanOrEqual(1);
    expect(collected[collected.length - 1]!.event).toBe('route_change');

    history.pushState({}, '', '/');
  });

  it('route_change event includes prev_url in properties', async () => {
    const prevUrl = location.href;
    history.pushState({}, '', '/destination');
    await nextFrame();
    expect(collected[0]!.properties?.['prev_url']).toBe(prevUrl);
    history.pushState({}, '', '/');
  });

  it('routes prev_url through sanitiseUrl: query-string PII is redacted, never leaked raw', async () => {
    history.replaceState({}, '', '/account?token=topsecret&email=a@b.com&page=2');
    await nextFrame();
    collected = [];

    history.pushState({}, '', '/next');
    await nextFrame();

    const prevUrl = String(collected[0]!.properties?.['prev_url']);
    expect(prevUrl).not.toContain('topsecret');
    expect(prevUrl).not.toContain('a@b.com');
    expect(prevUrl).toMatch(/token=%5Bredacted%5D|token=\[redacted\]/i);
    expect(prevUrl).toMatch(/email=%5Bredacted%5D|email=\[redacted\]/i);
    expect(prevUrl).toContain('page=2');

    history.pushState({}, '', '/');
  });

  it('broadcasts the sf:nav lifecycle event after a route change', async () => {
    const onNav = vi.fn();
    window.addEventListener(NAV_EVENT, onNav);
    history.pushState({}, '', '/nav-target');
    await nextFrame();
    expect(onNav).toHaveBeenCalled();
    window.removeEventListener(NAV_EVENT, onNav);
    history.pushState({}, '', '/');
  });

  it('destroy() restores the original history.pushState', async () => {
    collector.destroy();
    const countBefore = collected.length;
    history.pushState({}, '', '/after-destroy');
    await nextFrame();
    expect(collected.length).toBe(countBefore);
    history.pushState({}, '', '/');
  });

  it('destroy() is idempotent (second call does not throw)', () => {
    collector.destroy();
    expect(() => collector.destroy()).not.toThrow();
  });
});
