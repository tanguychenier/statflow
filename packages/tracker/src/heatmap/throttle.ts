/**
 * Statflow Tracker — Time-based throttle with optional rAF alignment.
 *
 * Pointer events fire far faster than the heatmap needs to sample. This
 * throttle admits at most one call per `intervalMs` and defers the actual work
 * to the next animation frame, so sampling reads layout values (`scrollX`,
 * `scrollWidth`) at a paint-consistent moment and never blocks the input
 * handler (design §8.2).
 *
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

export interface Throttled<A extends unknown[]> {
  (...args: A): void;
  cancel(): void;
}

const raf = (cb: () => void): number =>
  typeof requestAnimationFrame === 'function'
    ? requestAnimationFrame(cb)
    : (setTimeout(cb, 16) as unknown as number);

const cancelRaf = (handle: number): void => {
  if (typeof cancelAnimationFrame === 'function') cancelAnimationFrame(handle);
  else clearTimeout(handle);
};

/** `now()` — monotonic clock, falling back to Date when performance is absent. */
const now = (): number =>
  typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : Date.now();

export function rafThrottle<A extends unknown[]>(
  fn: (...args: A) => void,
  intervalMs: number,
): Throttled<A> {
  let last = -Infinity;
  let frame: number | undefined;
  let scheduled = false;
  let queued: A | undefined;

  // `scheduled` is the source of truth for "a frame is pending", tracked
  // separately from the rAF handle: a synchronous rAF (test doubles, the
  // setTimeout fallback under fake timers) runs `run` before `raf` returns, so
  // relying on the returned handle to detect pending work would clobber the
  // cleared state and wedge the throttle after one call.
  const run = (): void => {
    scheduled = false;
    frame = undefined;
    if (queued === undefined) return;
    last = now();
    const args = queued;
    queued = undefined;
    fn(...args);
  };

  const throttled = ((...args: A): void => {
    queued = args;
    if (now() - last < intervalMs) return;
    if (scheduled) return;
    scheduled = true;
    frame = raf(run);
  }) as Throttled<A>;

  throttled.cancel = (): void => {
    if (frame !== undefined) cancelRaf(frame);
    frame = undefined;
    scheduled = false;
    queued = undefined;
  };

  return throttled;
}
