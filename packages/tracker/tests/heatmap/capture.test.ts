import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createCaptureEngine } from '../../src/heatmap/capture.js';
import { resolveHeatmapConfig } from '../../src/heatmap/config.js';

let clock = 0;

/** rAF runs synchronously so throttled samples land in the buffer immediately. */
function syncRaf(cb: () => void): number {
  cb();
  return 1;
}

function setDocSize(w: number, h: number): void {
  Object.defineProperty(document.body, 'scrollWidth', { value: w, configurable: true });
  Object.defineProperty(document.body, 'scrollHeight', { value: h, configurable: true });
}

function setViewport(w: number, h: number): void {
  Object.defineProperty(window, 'innerWidth', { value: w, configurable: true });
  Object.defineProperty(window, 'innerHeight', { value: h, configurable: true });
}

beforeEach(() => {
  clock = 1000;
  vi.spyOn(performance, 'now').mockImplementation(() => clock);
  vi.stubGlobal('requestAnimationFrame', syncRaf);
  vi.stubGlobal('cancelAnimationFrame', () => {});
  setDocSize(1000, 2000);
  setViewport(800, 600);
  Object.defineProperty(window, 'scrollX', { value: 0, configurable: true });
  Object.defineProperty(window, 'scrollY', { value: 0, configurable: true });
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

function fireMouseMove(x: number, y: number): void {
  window.dispatchEvent(new MouseEvent('mousemove', { clientX: x, clientY: y }));
}

describe('createCaptureEngine', () => {
  it('collect returns undefined before any activity', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    expect(engine.collect()).toBeUndefined();
    engine.stop();
  });

  it('samples a mousemove into a document-relative point with percentages', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    fireMouseMove(100, 200);
    const batch = engine.collect();
    expect(batch).toBeDefined();
    expect(batch!.points).toHaveLength(1);
    const pt = batch!.points[0]!;
    expect(pt.x).toBe(100);
    expect(pt.y).toBe(200);
    expect(pt.xp).toBeCloseTo(10, 5); // 100 / 1000 * 100
    expect(pt.yp).toBeCloseTo(10, 5); // 200 / 2000 * 100
    expect(pt.k).toBe('m');
    engine.stop();
  });

  it('records a relative timestamp from capture start', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    clock = 1450;
    fireMouseMove(10, 10);
    const batch = engine.collect();
    expect(batch!.points[0]!.t).toBe(450);
    expect(batch!.duration_ms).toBe(450);
    engine.stop();
  });

  it('throttles rapid mousemove events to one per interval', () => {
    const engine = createCaptureEngine(
      resolveHeatmapConfig({ siteKey: 'stk', sampleIntervalMs: 50 }),
    );
    engine.start();
    fireMouseMove(1, 1);
    clock = 1010; // within the 50 ms window
    fireMouseMove(2, 2);
    fireMouseMove(3, 3);
    const batch = engine.collect();
    expect(batch!.points).toHaveLength(1);
    engine.stop();
  });

  it('captures click coordinates with kind c', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    window.dispatchEvent(new MouseEvent('click', { clientX: 500, clientY: 1000 }));
    const batch = engine.collect();
    expect(batch!.points.some((p) => p.k === 'c')).toBe(true);
    engine.stop();
  });

  it('records a scroll sample with kind s when the depth bucket changes', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    Object.defineProperty(window, 'scrollY', { value: 500, configurable: true });
    window.dispatchEvent(new Event('scroll'));
    const batch = engine.collect();
    expect(batch!.points.some((p) => p.k === 's')).toBe(true);
    engine.stop();
  });

  it('does not record duplicate scroll samples for the same depth bucket', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    Object.defineProperty(window, 'scrollY', { value: 500, configurable: true });
    window.dispatchEvent(new Event('scroll'));
    clock = 2000;
    window.dispatchEvent(new Event('scroll'));
    const batch = engine.collect();
    expect(batch!.points.filter((p) => p.k === 's')).toHaveLength(1);
    engine.stop();
  });

  it('captures touchmove via the first touch point', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    const touch = { clientX: 300, clientY: 400 } as Touch;
    window.dispatchEvent(new TouchEvent('touchmove', { touches: [touch] }));
    const batch = engine.collect();
    expect(batch!.points.some((p) => p.x === 300 && p.y === 400)).toBe(true);
    engine.stop();
  });

  it('ignores a touchmove with no touch points', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    window.dispatchEvent(new TouchEvent('touchmove', { touches: [] }));
    expect(engine.collect()).toBeUndefined();
    engine.stop();
  });

  it('caps retained points at maxPoints, keeping the most recent', () => {
    const engine = createCaptureEngine(
      resolveHeatmapConfig({ siteKey: 'stk', maxPoints: 3, sampleIntervalMs: 0 }),
    );
    engine.start();
    for (let i = 1; i <= 6; i++) {
      clock += 10;
      fireMouseMove(i, i);
    }
    const batch = engine.collect();
    expect(batch!.points).toHaveLength(3);
    expect(batch!.points.map((p) => p.x)).toEqual([4, 5, 6]);
    engine.stop();
  });

  it('clears the buffer after collect', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    fireMouseMove(10, 10);
    engine.collect();
    expect(engine.collect()).toBeUndefined();
    engine.stop();
  });

  it('drops samples when sampleRate is 0', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk', sampleRate: 0 }));
    engine.start();
    fireMouseMove(10, 10);
    expect(engine.collect()).toBeUndefined();
    engine.stop();
  });

  it('retains samples when sampleRate is a passing fraction', () => {
    vi.spyOn(Math, 'random').mockReturnValue(0.1);
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk', sampleRate: 0.5 }));
    engine.start();
    fireMouseMove(10, 10);
    expect(engine.collect()!.points).toHaveLength(1);
    engine.stop();
  });

  it('discards samples when the fractional roll fails', () => {
    vi.spyOn(Math, 'random').mockReturnValue(0.9);
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk', sampleRate: 0.5 }));
    engine.start();
    fireMouseMove(10, 10);
    expect(engine.collect()).toBeUndefined();
    engine.stop();
  });

  it('stop detaches listeners so further moves are not captured', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    engine.start();
    engine.stop();
    fireMouseMove(10, 10);
    expect(engine.collect()).toBeUndefined();
  });

  it('start is idempotent and does not double-register', () => {
    const engine = createCaptureEngine(
      resolveHeatmapConfig({ siteKey: 'stk', sampleIntervalMs: 0 }),
    );
    engine.start();
    engine.start();
    fireMouseMove(10, 10);
    expect(engine.collect()!.points).toHaveLength(1);
    engine.stop();
  });

  it('stop is safe to call without start', () => {
    const engine = createCaptureEngine(resolveHeatmapConfig({ siteKey: 'stk' }));
    expect(() => engine.stop()).not.toThrow();
  });
});
