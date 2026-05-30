import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createEngagementCollector } from '../../src/collectors/engagement.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

function setVisibility(state: 'visible' | 'hidden'): void {
  Object.defineProperty(document, 'visibilityState', { value: state, configurable: true });
}

describe('createEngagementCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createEngagementCollector>;

  beforeEach(() => {
    vi.useFakeTimers();
    resetSeq();
    setVisibility('visible');
    collected = [];
    collector = createEngagementCollector(resolveConfig({ siteKey: 'stk_eng', engagementIntervalMs: 10_000 }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
    vi.useRealTimers();
  });

  it('emits a heartbeat with active time after a qualifying interaction', () => {
    window.dispatchEvent(new Event('mousemove'));
    vi.advanceTimersByTime(10_000);

    const beat = collected.find((e) => e.event === 'engagement');
    expect(beat).toBeDefined();
    expect(beat!.behavioral?.engagement_time_ms).toBeGreaterThan(0);
    expect(beat!.properties?.['on_unload']).toBe(false);
    expect(beat!.properties?.['intervals']).toBe(1);
  });

  it('does not accrue active time while the tab is hidden', () => {
    setVisibility('hidden');
    window.dispatchEvent(new Event('keydown'));
    vi.advanceTimersByTime(10_000);

    const beats = collected.filter((e) => e.event === 'engagement');
    // hidden the whole interval → no active time → no heartbeat
    expect(beats).toHaveLength(0);
  });

  it('does not accrue active time without a recent interaction', () => {
    // no interaction dispatched
    vi.advanceTimersByTime(10_000);
    expect(collected.filter((e) => e.event === 'engagement')).toHaveLength(0);
  });

  it('emits a final on_unload heartbeat on pagehide', () => {
    window.dispatchEvent(new Event('mousemove'));
    vi.advanceTimersByTime(3_000);
    window.dispatchEvent(new Event('pagehide'));

    const unloadBeat = collected.find((e) => e.event === 'engagement' && e.properties?.['on_unload'] === true);
    expect(unloadBeat).toBeDefined();
  });

  it('emits an on_unload heartbeat when the tab becomes hidden', () => {
    window.dispatchEvent(new Event('mousemove'));
    vi.advanceTimersByTime(2_000);
    setVisibility('hidden');
    document.dispatchEvent(new Event('visibilitychange'));

    expect(collected.some((e) => e.event === 'engagement' && e.properties?.['on_unload'] === true)).toBe(true);
  });

  it('accumulates total active time across intervals', () => {
    window.dispatchEvent(new Event('scroll'));
    vi.advanceTimersByTime(10_000);
    window.dispatchEvent(new Event('scroll'));
    vi.advanceTimersByTime(10_000);

    const beats = collected.filter((e) => e.event === 'engagement');
    expect(beats.length).toBeGreaterThanOrEqual(2);
    const last = beats[beats.length - 1]!;
    expect(last.properties?.['active_ms']).toBeGreaterThan(last.behavioral?.engagement_time_ms as number);
  });

  it('reset() zeroes the accumulators so interval numbering restarts', () => {
    window.dispatchEvent(new Event('click'));
    vi.advanceTimersByTime(10_000);
    const before = collected.find((e) => e.event === 'engagement')!;
    expect(before.properties?.['intervals']).toBe(1);

    collector.reset();
    collected = [];
    window.dispatchEvent(new Event('click'));
    vi.advanceTimersByTime(10_000);
    const after = collected.find((e) => e.event === 'engagement')!;
    expect(after.properties?.['intervals']).toBe(1);
    expect(after.properties?.['total_ms']).toBeLessThanOrEqual(10_000);
  });

  it('destroy() clears the heartbeat timer', () => {
    collector.destroy();
    collected = [];
    window.dispatchEvent(new Event('mousemove'));
    vi.advanceTimersByTime(30_000);
    expect(collected).toHaveLength(0);
  });
});
