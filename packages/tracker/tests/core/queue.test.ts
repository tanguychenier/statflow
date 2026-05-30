import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EventQueue } from '../../src/core/queue.js';
import { resetUnloading, isUnloading, markUnloading } from '../../src/core/transport.js';
import { resolveConfig } from '../../src/core/config.js';
import type { StatflowEvent } from '../../src/core/config.js';
import { makeEvent } from '../helpers.js';

function makeConfig(overrides = {}): ReturnType<typeof resolveConfig> {
  return resolveConfig({
    siteKey: 'stk_test',
    apiBase: 'https://example.com',
    apiPath: '/api/v1/events',
    batchSize: 3,
    batchIntervalMs: 10_000,
    ...overrides,
  });
}

describe('EventQueue', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    resetUnloading();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
  });

  afterEach(() => {
    vi.useRealTimers();
    resetUnloading();
    vi.restoreAllMocks();
  });

  it('starts with length 0', () => {
    const q = new EventQueue(makeConfig());
    expect(q.length).toBe(0);
  });

  it('length reflects pushed events', () => {
    const q = new EventQueue(makeConfig());
    q.push(makeEvent());
    expect(q.length).toBe(1);
    q.push(makeEvent());
    expect(q.length).toBe(2);
  });

  it('drops events filtered out by beforeSend', () => {
    const config = makeConfig({ beforeSend: () => null });
    const q = new EventQueue(config);
    q.push(makeEvent());
    expect(q.length).toBe(0);
  });

  it('transforms events via beforeSend', () => {
    const config = makeConfig({
      beforeSend: (e: StatflowEvent) => ({ ...e, event: 'mutated' }),
    });
    const q = new EventQueue(config);
    q.push(makeEvent('original'));
    expect(q.length).toBe(1);
  });

  it('flushes immediately when batchSize is reached', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const config = makeConfig({ batchSize: 2 });
    const q = new EventQueue(config);

    q.push(makeEvent());
    q.push(makeEvent()); // triggers auto-flush

    // Drain the microtask queue
    await Promise.resolve();
    await Promise.resolve();

    expect(fetchSpy).toHaveBeenCalledOnce();
    expect(q.length).toBe(0);
  });

  it('flushes on interval timer', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const config = makeConfig({ batchIntervalMs: 5_000 });
    const q = new EventQueue(config);
    q.start();

    q.push(makeEvent());
    expect(fetchSpy).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(5_000);
    await Promise.resolve();

    expect(fetchSpy).toHaveBeenCalledOnce();
    q.destroy();
  });

  it('does not flush on interval when queue is empty', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const q = new EventQueue(makeConfig({ batchIntervalMs: 1_000 }));
    q.start();

    await vi.advanceTimersByTimeAsync(3_000);
    expect(fetchSpy).not.toHaveBeenCalled();
    q.destroy();
  });

  it('destroy() clears the interval', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const q = new EventQueue(makeConfig({ batchIntervalMs: 1_000 }));
    q.start();
    q.push(makeEvent());
    q.destroy();

    await vi.advanceTimersByTimeAsync(5_000);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('explicit flush() drains the queue', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const q = new EventQueue(makeConfig());
    q.push(makeEvent());
    q.push(makeEvent());

    await q.flush(false);
    expect(fetchSpy).toHaveBeenCalledOnce();
    expect(q.length).toBe(0);
  });

  it('flush() is a no-op when queue is empty', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, { status: 200 }),
    );
    const q = new EventQueue(makeConfig());
    await q.flush(false);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('concurrent flush is deferred and runs after the first completes', async () => {
    let resolveFirst!: () => void;
    const fetchSpy = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementationOnce(
        () => new Promise<Response>((res) => { resolveFirst = () => res(new Response(null, { status: 200 })); }),
      )
      .mockResolvedValue(new Response(null, { status: 200 }));

    const q = new EventQueue(makeConfig());
    q.push(makeEvent('a'));
    q.push(makeEvent('b'));

    const flush1 = q.flush(false);
    // The first fetch is in-flight; push another event and request a second flush
    q.push(makeEvent('c'));
    const flush2 = q.flush(false);

    // Still only one fetch call
    expect(fetchSpy).toHaveBeenCalledTimes(1);

    // Resolve the first fetch and wait for both flushes to settle
    resolveFirst();
    await flush1;
    await flush2;
    await Promise.resolve();

    expect(fetchSpy).toHaveBeenCalledTimes(2);
    expect(q.length).toBe(0);
  });
});

describe('EventQueue — unload latch lifecycle (resume after first hide)', () => {
  beforeEach(() => {
    resetUnloading();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
  });

  afterEach(() => {
    resetUnloading();
    vi.restoreAllMocks();
  });

  function hide(): void {
    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
    window.dispatchEvent(new Event('visibilitychange'));
  }

  function show(): void {
    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
    window.dispatchEvent(new Event('visibilitychange'));
  }

  it('sets the unload latch on tab hide', () => {
    const q = new EventQueue(makeConfig());
    q.start();
    hide();
    expect(isUnloading()).toBe(true);
    q.destroy();
    show();
  });

  it('RESETS the unload latch when the tab becomes visible again — tracking resumes', () => {
    const q = new EventQueue(makeConfig());
    q.start();

    hide();
    expect(isUnloading()).toBe(true);

    show();
    expect(isUnloading()).toBe(false); // would stay true under the regressed latch

    q.destroy();
  });

  it('RESETS the unload latch on pageshow (bfcache restore)', () => {
    const q = new EventQueue(makeConfig());
    q.start();

    markUnloading();
    expect(isUnloading()).toBe(true);

    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    expect(isUnloading()).toBe(false);

    q.destroy();
  });

  it('destroy() detaches the lifecycle listeners', () => {
    const q = new EventQueue(makeConfig());
    q.start();
    q.destroy();

    // After teardown, a pageshow must not flip the (externally set) latch.
    markUnloading();
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: false }));
    expect(isUnloading()).toBe(true);
  });
});
