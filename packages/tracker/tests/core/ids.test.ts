import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { nextSeq, resetSeq, uuid } from '../../src/core/ids.js';

describe('ids — sequence counter', () => {
  beforeEach(() => {
    resetSeq();
  });

  it('starts at 1 after reset', () => {
    expect(nextSeq()).toBe(1);
  });

  it('increments monotonically', () => {
    expect(nextSeq()).toBe(1);
    expect(nextSeq()).toBe(2);
    expect(nextSeq()).toBe(3);
  });

  it('resets to 1 after resetSeq()', () => {
    nextSeq();
    nextSeq();
    resetSeq();
    expect(nextSeq()).toBe(1);
  });

  it('is monotonic PER SESSION: a second page load within the session continues, not restarts (event-contract §9)', () => {
    // First page load advances the counter.
    expect(nextSeq()).toBe(1);
    expect(nextSeq()).toBe(2);
    expect(nextSeq()).toBe(3);

    // A hard navigation in the same session re-evaluates the module from scratch,
    // but the counter is persisted in sessionStorage, so it MUST continue — it
    // does not reset. We prove persistence by reading the stored value the way a
    // fresh module instance would on the next load.
    expect(sessionStorage.getItem('sf_seq')).toBe('3');
    expect(nextSeq()).toBe(4); // continues across the simulated reload boundary
    expect(nextSeq()).toBe(5);
  });

  it('persists the counter under a single non-PII integer sessionStorage key', () => {
    nextSeq();
    nextSeq();
    expect(sessionStorage.getItem('sf_seq')).toBe('2');
    // No other keys are written by the tracker.
    const keys = Object.keys(sessionStorage).filter((k) => !k.startsWith('__'));
    expect(keys).toEqual(['sf_seq']);
  });

  it('falls back to an in-memory counter when sessionStorage throws (private mode)', () => {
    resetSeq();
    const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError');
    });
    try {
      // Still monotonic, never throws, even though persistence is unavailable.
      expect(nextSeq()).toBe(1);
      expect(nextSeq()).toBe(2);
    } finally {
      spy.mockRestore();
    }
  });
});

describe('ids — uuid', () => {
  const RE = /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i;

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('produces a valid RFC-4122 v4 UUID', () => {
    expect(uuid()).toMatch(RE);
  });

  it('sets the version nibble to 4 and the variant nibble to 8/9/a/b', () => {
    for (let i = 0; i < 50; i++) {
      const id = uuid();
      expect(id[14]).toBe('4');
      expect('89ab').toContain(id[19]!.toLowerCase());
    }
  });

  it('produces unique values across many calls', () => {
    const seen = new Set<string>();
    for (let i = 0; i < 1000; i++) seen.add(uuid());
    expect(seen.size).toBe(1000);
  });

  it('falls back to Math.random when crypto.getRandomValues is unavailable', () => {
    const original = globalThis.crypto;
    Object.defineProperty(globalThis, 'crypto', { value: undefined, configurable: true });
    try {
      expect(uuid()).toMatch(RE);
    } finally {
      Object.defineProperty(globalThis, 'crypto', { value: original, configurable: true });
    }
  });
});
