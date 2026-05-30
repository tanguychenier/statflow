import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { init } from '../../src/heatmap/index.js';
import { resetSeq } from '../../src/core/ids.js';

let clock = 0;

function syncRaf(cb: () => void): number {
  cb();
  return 1;
}

function setDocSize(w: number, h: number): void {
  Object.defineProperty(document.body, 'scrollWidth', { value: w, configurable: true });
  Object.defineProperty(document.body, 'scrollHeight', { value: h, configurable: true });
}

/** Parse the JSON body posted to the ingestion endpoint by the transport. */
function postedBatch(fetchMock: ReturnType<typeof vi.fn>): {
  events: Array<{ e: string; k: string; props?: Record<string, unknown> }>;
} {
  const body = fetchMock.mock.calls[0]![1].body as string;
  return JSON.parse(body);
}

beforeEach(() => {
  resetSeq();
  clock = 1000;
  vi.spyOn(performance, 'now').mockImplementation(() => clock);
  vi.stubGlobal('requestAnimationFrame', syncRaf);
  vi.stubGlobal('cancelAnimationFrame', () => {});
  setDocSize(1000, 2000);
  Object.defineProperty(window, 'scrollX', { value: 0, configurable: true });
  Object.defineProperty(window, 'scrollY', { value: 0, configurable: true });
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  Object.defineProperty(navigator, 'doNotTrack', { value: null, configurable: true });
});

function fireMouseMove(x: number, y: number): void {
  window.dispatchEvent(new MouseEvent('mousemove', { clientX: x, clientY: y }));
}

describe('heatmap init — opt-out paths', () => {
  it('returns a no-op when DNT halts collection', () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', dnt: 'disable' });
    fireMouseMove(10, 10);
    return hm.flush().then(() => {
      expect(fetchMock).not.toHaveBeenCalled();
    });
  });

  it('proceeds when DNT is configured to be ignored', async () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', dnt: 'ignore' });
    fireMouseMove(10, 10);
    await hm.flush();
    expect(fetchMock).toHaveBeenCalledOnce();
    await hm.destroy();
  });

  it('returns a no-op when the session sample roll fails', async () => {
    vi.spyOn(Math, 'random').mockReturnValue(0.9);
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', sampleRate: 0.5 });
    fireMouseMove(10, 10);
    await hm.flush();
    expect(fetchMock).not.toHaveBeenCalled();
    await hm.destroy();
  });
});

describe('heatmap init — batch assembly', () => {
  it('flushes a heatmap_batch event in the compact wire format', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk_live', apiBase: 'https://x.test', apiPath: '/api/v1/events' });

    fireMouseMove(100, 200);
    await hm.flush();

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, opts] = fetchMock.mock.calls[0]!;
    expect(url).toBe('https://x.test/api/v1/events');
    expect(opts.headers['Content-Type']).toBe('text/plain');

    const payload = postedBatch(fetchMock);
    expect(payload.events).toHaveLength(1);
    const ev = payload.events[0]!;
    expect(ev.e).toBe('heatmap_batch');
    expect(ev.k).toBe('stk_live');
    const points = ev.props!['points'] as Array<{ x: number; k: string }>;
    expect(points[0]!.x).toBe(100);
    expect(ev.props!['sample_rate']).toBe(1);
    expect(typeof ev.props!['duration_ms']).toBe('number');

    await hm.destroy();
  });

  it('does not POST when there are no points to flush', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk' });
    await hm.flush();
    expect(fetchMock).not.toHaveBeenCalled();
    await hm.destroy();
  });

  it('drains the buffer so a second flush sends nothing new', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk' });
    fireMouseMove(10, 10);
    await hm.flush();
    await hm.flush();
    expect(fetchMock).toHaveBeenCalledOnce();
    await hm.destroy();
  });
});

describe('heatmap init — scheduling and lifecycle', () => {
  it('auto-flushes on the configured interval', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', flushIntervalMs: 1000 });
    fireMouseMove(10, 10);
    await vi.advanceTimersByTimeAsync(1000);
    expect(fetchMock).toHaveBeenCalledOnce();
    await hm.destroy();
    vi.useRealTimers();
  });

  it('flushes when the tab becomes hidden', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    const beaconMock = vi.fn().mockReturnValue(true);
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(navigator, 'sendBeacon').mockImplementation(beaconMock);
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');

    const hm = init({ siteKey: 'stk' });
    fireMouseMove(10, 10);
    window.dispatchEvent(new Event('visibilitychange'));
    await Promise.resolve();
    // Either transport path drains the buffer; assert one of them ran.
    expect(beaconMock.mock.calls.length + fetchMock.mock.calls.length).toBeGreaterThanOrEqual(1);
    await hm.destroy();
  });

  it('does not flush on visibilitychange while the tab stays visible', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    const hm = init({ siteKey: 'stk' });
    fireMouseMove(10, 10);
    window.dispatchEvent(new Event('visibilitychange'));
    await Promise.resolve();
    expect(fetchMock).not.toHaveBeenCalled();
    await hm.destroy();
  });

  it('flushes on pagehide', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    const beaconMock = vi.fn().mockReturnValue(true);
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(navigator, 'sendBeacon').mockImplementation(beaconMock);
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    const hm = init({ siteKey: 'stk' });
    fireMouseMove(10, 10);
    window.dispatchEvent(new Event('pagehide'));
    await Promise.resolve();
    expect(beaconMock.mock.calls.length + fetchMock.mock.calls.length).toBeGreaterThanOrEqual(1);
    await hm.destroy();
  });

  it('destroy flushes remaining points and detaches everything', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', flushIntervalMs: 100000 });
    fireMouseMove(10, 10);
    await hm.destroy();
    expect(fetchMock).toHaveBeenCalledOnce();
    // Timer is cleared: advancing past the interval triggers no further flush.
    fetchMock.mockClear();
    fireMouseMove(20, 20);
    await vi.advanceTimersByTimeAsync(100000);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.useRealTimers();
  });

  it('swallows transport errors during a scheduled flush', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn().mockRejectedValue(new Error('network'));
    vi.stubGlobal('fetch', fetchMock);
    const hm = init({ siteKey: 'stk', flushIntervalMs: 1000 });
    fireMouseMove(10, 10);
    await expect(vi.advanceTimersByTimeAsync(1000)).resolves.not.toThrow();
    await hm.destroy().catch(() => {});
    vi.useRealTimers();
  });
});
