import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { rafThrottle } from '../../src/heatmap/throttle.js';

/**
 * Drives the throttle deterministically: a controllable clock backs
 * performance.now() and rAF resolves synchronously on the next microtask via a
 * manual queue, so each test advances time explicitly.
 */
let clock = 0;
let frameQueue: Array<() => void>;

function flushFrames(): void {
  const pending = frameQueue;
  frameQueue = [];
  pending.forEach((cb) => cb());
}

beforeEach(() => {
  clock = 0;
  frameQueue = [];
  vi.spyOn(performance, 'now').mockImplementation(() => clock);
  vi.stubGlobal('requestAnimationFrame', (cb: () => void): number => {
    frameQueue.push(cb);
    return frameQueue.length;
  });
  vi.stubGlobal('cancelAnimationFrame', (handle: number): void => {
    frameQueue[handle - 1] = (): void => {};
  });
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('rafThrottle', () => {
  it('invokes the leading call on the next frame', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    expect(fn).not.toHaveBeenCalled();
    flushFrames();
    expect(fn).toHaveBeenCalledTimes(1);
    expect(fn).toHaveBeenCalledWith('a');
  });

  it('drops intermediate calls inside the interval, keeping the latest args', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    flushFrames();
    // Still within the 50 ms window — these must not schedule a new frame.
    clock = 20;
    t('b');
    t('c');
    flushFrames();
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('admits a new call once the interval has elapsed', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    flushFrames();
    clock = 60;
    t('b');
    flushFrames();
    expect(fn).toHaveBeenCalledTimes(2);
    expect(fn).toHaveBeenLastCalledWith('b');
  });

  it('coalesces a burst into a single frame execution', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('1');
    t('2');
    t('3');
    flushFrames();
    expect(fn).toHaveBeenCalledTimes(1);
    expect(fn).toHaveBeenCalledWith('3');
  });

  it('cancel prevents a pending frame from firing', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    t.cancel();
    flushFrames();
    expect(fn).not.toHaveBeenCalled();
  });

  it('cancel is a no-op when nothing is pending', () => {
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    expect(() => t.cancel()).not.toThrow();
  });

  it('falls back to setTimeout when rAF is unavailable', () => {
    vi.useFakeTimers();
    vi.stubGlobal('requestAnimationFrame', undefined);
    vi.stubGlobal('cancelAnimationFrame', undefined);
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    vi.advanceTimersByTime(20);
    expect(fn).toHaveBeenCalledTimes(1);
    expect(fn).toHaveBeenCalledWith('a');
    vi.useRealTimers();
  });

  it('cancel clears the setTimeout fallback handle', () => {
    vi.useFakeTimers();
    vi.stubGlobal('requestAnimationFrame', undefined);
    vi.stubGlobal('cancelAnimationFrame', undefined);
    const fn = vi.fn();
    const t = rafThrottle(fn, 50);
    t('a');
    t.cancel();
    vi.advanceTimersByTime(50);
    expect(fn).not.toHaveBeenCalled();
    vi.useRealTimers();
  });
});
