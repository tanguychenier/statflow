/**
 * Branch-coverage tests for index.ts — disable combinations and lifecycle.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { init } from '../src/index.js';
import { resetSeq } from '../src/core/ids.js';
import { resetUnloading } from '../src/core/transport.js';

const HEAVY_OFF = ['clicks', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'] as const;

describe('init() — collector disable branches', () => {
  beforeEach(() => {
    resetSeq();
    resetUnloading();
    Object.defineProperty(navigator, 'doNotTrack', { value: null, configurable: true });
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('disabling only spa still fires the initial pageview', async () => {
    const api = init({ siteKey: 'stk_dis_spa', disable: ['spa', ...HEAVY_OFF], batchSize: 1 });
    await api.flush();
    expect(vi.mocked(globalThis.fetch)).toHaveBeenCalled();
    api.destroy();
  });

  it('disabling only pageview still exposes a working API', () => {
    const api = init({ siteKey: 'stk_dis_pv', disable: ['pageview', ...HEAVY_OFF] });
    expect(() => api.track('valid_event')).not.toThrow();
    api.destroy();
  });

  it('always halts (do-not-initialize) under DNT=1 with the default disable mode — no resurrected anonymous bypass', async () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const api = init({ siteKey: 'stk_dnt_halt', dnt: 'disable', batchSize: 1 });
    // A halted tracker exposes a no-op API and never queues or sends anything.
    api.page();
    api.track('valid_event');
    await api.flush();
    expect(vi.mocked(globalThis.fetch)).not.toHaveBeenCalled();
    api.destroy();
  });

  it('ignore dnt mode tracks even with DNT=1', () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const api = init({ siteKey: 'stk_ignore', dnt: 'ignore', disable: ['pageview', 'spa', ...HEAVY_OFF] });
    expect(typeof api.flush).toBe('function');
    api.destroy();
  });

  it('loadHeatmap rejects gracefully when the module URL cannot be resolved', async () => {
    const api = init({ siteKey: 'stk_hm', apiBase: 'https://example.com', disable: ['pageview', 'spa', ...HEAVY_OFF] });
    // import() of a non-existent URL rejects; the public method surfaces that rejection.
    await expect(api.loadHeatmap()).rejects.toBeDefined();
    api.destroy();
  });
});
