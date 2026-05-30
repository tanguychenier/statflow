import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  sendBatch,
  markUnloading,
  isUnloading,
  resetUnloading,
} from '../../src/core/transport.js';
import { makeEvent } from '../helpers.js';

const URL = 'https://example.com/api/v1/events';

describe('transport — unloading state', () => {
  afterEach(() => {
    resetUnloading();
  });

  it('starts as not unloading', () => {
    expect(isUnloading()).toBe(false);
  });

  it('markUnloading() sets the flag', () => {
    markUnloading();
    expect(isUnloading()).toBe(true);
  });

  it('resetUnloading() clears the flag', () => {
    markUnloading();
    resetUnloading();
    expect(isUnloading()).toBe(false);
  });
});

describe('sendBatch', () => {
  beforeEach(() => {
    resetUnloading();
  });

  afterEach(() => {
    resetUnloading();
    vi.restoreAllMocks();
  });

  it('does nothing when given an empty batch', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    await sendBatch([], URL);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('serialises the unified batch envelope with root key "events" and short wire keys', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    await sendBatch([makeEvent()], URL);

    expect(fetchSpy).toHaveBeenCalledOnce();
    const [url, init] = fetchSpy.mock.calls[0]!;
    expect(url).toBe(URL);
    expect((init as RequestInit).method).toBe('POST');
    expect((init as RequestInit).keepalive).toBe(true);
    expect((init as RequestInit).headers).toMatchObject({ 'Content-Type': 'text/plain' });

    const body = JSON.parse((init as RequestInit).body as string) as {
      events: Array<Record<string, unknown>>;
      sent_at: number;
      sdk: string;
      sdk_v: string;
    };
    expect(body.sdk).toBe('tracker');
    expect(typeof body.sent_at).toBe('number');
    expect(typeof body.sdk_v).toBe('string');
    expect(body.events).toHaveLength(1);

    const wire = body.events[0]!;
    // short keys only — no long internal keys leak onto the wire
    expect(wire['e']).toBe('pageview');
    expect(wire['eid']).toBe('8f14e45f-ce64-4f1a-9b2d-2c3a4b5c6d7e');
    expect(wire['k']).toBe('stk_test');
    expect(wire['u']).toBe('https://example.com/pricing');
    expect(wire['p']).toBe('/pricing');
    expect(wire['h']).toBe('example.com');
    expect(wire['vw']).toBe(1280);
    expect(typeof wire['ts']).toBe('number');
    expect(wire['event']).toBeUndefined();
    expect(wire['url']).toBeUndefined();
    expect(wire['pathname']).toBeUndefined();
    expect(wire['pid']).toBeUndefined();
  });

  it('maps behavioral signals into the wire "b" block', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));
    await sendBatch([makeEvent('click', { behavioral: { click_x: 5, click_y: 9, is_rage_click: true } })], URL);
    const body = JSON.parse((fetchSpy.mock.calls[0]![1] as RequestInit).body as string) as {
      events: Array<{ b?: Record<string, unknown> }>;
    };
    expect(body.events[0]!.b).toEqual({ cx: 5, cy: 9, rc: true });
  });

  it('uses sendBeacon on the unload path when beacon returns true', async () => {
    markUnloading();
    const beaconSpy = vi.spyOn(navigator, 'sendBeacon').mockReturnValue(true);
    const fetchSpy = vi.spyOn(globalThis, 'fetch');

    await sendBatch([makeEvent()], URL);

    expect(beaconSpy).toHaveBeenCalledOnce();
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('falls back to fetch when sendBeacon returns false', async () => {
    markUnloading();
    vi.spyOn(navigator, 'sendBeacon').mockReturnValue(false);
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 200 }));

    await sendBatch([makeEvent()], URL);

    expect(fetchSpy).toHaveBeenCalledOnce();
  });

  it('retries once on network failure then drops', async () => {
    vi.useFakeTimers();
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Network error'));

    const promise = sendBatch([makeEvent()], URL, true);
    await vi.advanceTimersByTimeAsync(2_100);
    await promise;

    expect(fetchSpy).toHaveBeenCalledTimes(2);
    vi.useRealTimers();
  });

  it('does not retry on 4xx responses', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 400 }));
    await sendBatch([makeEvent()], URL);
    expect(fetchSpy).toHaveBeenCalledTimes(1);
  });

  it('retries once on 5xx response', async () => {
    vi.useFakeTimers();
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 500 }));

    const promise = sendBatch([makeEvent()], URL, true);
    await vi.advanceTimersByTimeAsync(2_100);
    await promise;

    expect(fetchSpy).toHaveBeenCalledTimes(2);
    vi.useRealTimers();
  });

  it('does not retry when retryOnce=false', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Network error'));
    await sendBatch([makeEvent()], URL, false);
    expect(fetchSpy).toHaveBeenCalledTimes(1);
  });
});
