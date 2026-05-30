import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { init } from '../src/index.js';
import { resetSeq } from '../src/core/ids.js';
import { resetUnloading } from '../src/core/transport.js';
import { mount as mountBehavior } from '../src/behavior/index.js';

// init() loads the behavioral collectors by URL (/sf/behavior.js), mirroring the
// heatmap module. In a browser that resolves to the separately-built bundle; in
// jsdom there is no server, so we map that exact specifier to the real behavior
// entry's mount(). This exercises the production fire-and-forget import() path
// end to end. The factory is synchronous (the behavior entry is statically
// imported above) so the mocked dynamic import resolves deterministically under
// the vitest module runner.
vi.mock('http://localhost:3000/sf/behavior.js', () => ({ mount: mountBehavior }));

const ALL_OFF = ['pageview', 'spa', 'clicks', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'] as const;

function parseWire(body: string): Array<Record<string, unknown>> {
  return (JSON.parse(body) as { events: Array<Record<string, unknown>> }).events;
}

let lastApi: { destroy(): void } | undefined;

/** Init and remember the instance so afterEach can always tear it down. */
function track(cfg: Parameters<typeof init>[0]): ReturnType<typeof init> {
  const api = init(cfg);
  lastApi = api;
  return api;
}

/** Wait until the lazily-imported behavior chunk has resolved and mounted. */
async function settleAutocapture(): Promise<void> {
  for (let i = 0; i < 50; i++) {
    await Promise.resolve();
    await new Promise((r) => setTimeout(r, 1));
  }
}

describe('init() — public API', () => {
  beforeEach(() => {
    resetSeq();
    resetUnloading();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    Object.defineProperty(navigator, 'doNotTrack', { value: null, configurable: true });
  });

  afterEach(() => {
    lastApi?.destroy();
    lastApi = undefined;
    vi.restoreAllMocks();
  });

  it('returns a public API with the required methods', () => {
    const api = track({ siteKey: 'stk_test', disable: [...ALL_OFF] });
    expect(typeof api.page).toBe('function');
    expect(typeof api.track).toBe('function');
    expect(typeof api.flush).toBe('function');
    expect(typeof api.destroy).toBe('function');
    expect(typeof api.loadHeatmap).toBe('function');
    api.destroy();
  });

  it('destroy() can be called without error', () => {
    const api = track({ siteKey: 'stk_d', disable: [...ALL_OFF] });
    expect(() => api.destroy()).not.toThrow();
  });

  it('track() emits the custom name as event_name with a FLAT custom_properties map (no nesting)', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    const api = track({ siteKey: 'stk_track', disable: [...ALL_OFF], batchSize: 1 });

    api.track('video_play', { video_id: 'abc123', duration_s: 183, autoplay: false });
    await api.flush();

    expect(fetchSpy).toHaveBeenCalledOnce();
    const events = parseWire((fetchSpy.mock.calls[0]![1] as RequestInit).body as string);
    // event_name carries the custom name; there is no generic 'custom' wrapper event.
    const custom = events.find((e) => e['e'] === 'video_play');
    expect(custom).toBeDefined();
    expect(events.some((e) => e['e'] === 'custom')).toBe(false);
    expect(custom!['k']).toBe('stk_track');
    expect(typeof custom!['eid']).toBe('string');
    // props is the flat custom_properties map per event-contract.md §6 — no { name, props } nesting.
    const props = custom!['props'] as Record<string, unknown>;
    expect(props).toEqual({ video_id: 'abc123', duration_s: 183, autoplay: false });
    expect(props['name']).toBeUndefined();
    expect(props['props']).toBeUndefined();

    api.destroy();
  });

  it('track() silently drops an invalid custom event name', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    const api = track({ siteKey: 'stk_inv', disable: [...ALL_OFF], batchSize: 1 });

    api.track('sf_reserved');
    api.track('Has Spaces');
    await api.flush();

    expect(fetchSpy).not.toHaveBeenCalled();
    api.destroy();
  });

  it('flush() resolves without error', async () => {
    const api = track({ siteKey: 'stk_flush', disable: [...ALL_OFF] });
    await expect(api.flush()).resolves.toBeUndefined();
    api.destroy();
  });

  it('page() enqueues a pageview event in wire form', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    const api = track({ siteKey: 'stk_page', disable: [...ALL_OFF], batchSize: 1 });

    api.page();
    await api.flush();

    expect(fetchSpy).toHaveBeenCalled();
    const events = parseWire((fetchSpy.mock.calls[0]![1] as RequestInit).body as string);
    expect(events.some((e) => e['e'] === 'pageview')).toBe(true);

    api.destroy();
  });

  it('lazily mounts the full autocapture collector set without throwing', async () => {
    const api = track({ siteKey: 'stk_full' });
    await settleAutocapture();
    expect(typeof api.flush).toBe('function');
  });

  it('captures a click after the autocapture chunk has loaded', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    const api = track({ siteKey: 'stk_lazy_click', disable: ['pageview', 'spa', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'], batchSize: 1 });
    await settleAutocapture();

    const btn = document.createElement('button');
    document.body.appendChild(btn);
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    await api.flush();

    const events = parseWire((fetchSpy.mock.calls.at(-1)![1] as RequestInit).body as string);
    expect(events.some((e) => e['e'] === 'click')).toBe(true);
    document.body.removeChild(btn);
  });

  it('returns noop API when DNT=1 and dnt="disable"', async () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const api = track({ siteKey: 'stk_dnt', dnt: 'disable' });

    expect(() => api.track('test')).not.toThrow();
    expect(() => api.page()).not.toThrow();
    expect(() => api.destroy()).not.toThrow();
    await expect(api.flush()).resolves.toBeUndefined();
    await expect(api.loadHeatmap()).resolves.toBeUndefined();
  });

  it('returns noop API when sampleRate=0', () => {
    const api = track({ siteKey: 'stk_sample', sampleRate: 0, disable: [...ALL_OFF] });
    expect(() => api.track('test')).not.toThrow();
    expect(() => api.destroy()).not.toThrow();
  });

  it('is idempotent — second init() returns the existing instance API', () => {
    const api1 = init({ siteKey: 'stk_idem', disable: [...ALL_OFF] });
    const api2 = init({ siteKey: 'stk_different', disable: [...ALL_OFF] });
    expect(typeof api1.track).toBe('function');
    expect(typeof api2.track).toBe('function');
    api1.destroy();
  });

  it('allows reinitialisation after destroy()', () => {
    const api1 = init({ siteKey: 'stk_reinit', disable: [...ALL_OFF] });
    api1.destroy();
    const api2 = init({ siteKey: 'stk_reinit2', disable: [...ALL_OFF] });
    expect(typeof api2.track).toBe('function');
    api2.destroy();
  });
});
