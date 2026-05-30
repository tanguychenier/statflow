/**
 * Additional branch-coverage tests for the SPA collector.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createSpaCollector } from '../../src/collectors/spa.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

const config = resolveConfig({ siteKey: 'stk_spa_branch' });

function nextFrame(): Promise<void> {
  return new Promise((resolve) => {
    requestAnimationFrame(() => setTimeout(resolve, 0));
  });
}

describe('SPA collector — replaceState branches', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createSpaCollector>;

  beforeEach(async () => {
    resetSeq();
    history.replaceState({}, '', '/');
    await nextFrame(); // drain any animation frame queued by a previous test
    collected = [];
    collector = createSpaCollector(config);
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
    vi.restoreAllMocks();
  });

  it('does NOT emit route_change on replaceState when path is unchanged', async () => {
    const url = location.href;
    history.replaceState({}, '', url);
    await nextFrame();
    const rcEvents = collected.filter(e => e.event === 'route_change' && e.properties?.['method'] === 'replaceState');
    expect(rcEvents).toHaveLength(0);
  });

  it('emits route_change on replaceState when path changes', async () => {
    const initialPath = location.pathname;
    history.replaceState({}, '', '/replaced-path-unique-123');
    await nextFrame();
    const rcEvents = collected.filter(e => e.event === 'route_change' && e.properties?.['method'] === 'replaceState');
    expect(rcEvents).toHaveLength(1);
    history.replaceState({}, '', initialPath);
  });

  it('emits route_change on hashchange when hash changes', async () => {
    history.pushState({}, '', '/page-for-hash');
    await nextFrame();
    collected = [];

    history.pushState({}, '', '/page-for-hash#new-section');
    await nextFrame();
    const rcEvents = collected.filter(e => e.event === 'route_change');
    expect(rcEvents.length).toBeGreaterThanOrEqual(1);
    history.pushState({}, '', '/');
  });

  it('destroy() is safe to call twice', () => {
    collector.destroy();
    expect(() => collector.destroy()).not.toThrow();
  });

  it('emits synchronously with duration 0 when requestAnimationFrame is unavailable', () => {
    collector.destroy();
    vi.stubGlobal('requestAnimationFrame', undefined);
    const c = createSpaCollector(config);
    const events: StatflowEvent[] = [];
    c.mount((e) => events.push(e));
    history.pushState({}, '', '/no-raf');
    const rc = events.find((e) => e.event === 'route_change')!;
    expect(rc).toBeDefined();
    expect(rc.properties?.['duration_ms']).toBe(0);
    c.destroy();
    vi.unstubAllGlobals();
    history.replaceState({}, '', '/');
  });
});
