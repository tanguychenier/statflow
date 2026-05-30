/**
 * Branch-coverage tests for envelope.ts — specifically the optional-chaining
 * paths that are hard to hit in the normal jsdom environment.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { buildEnvelope } from '../../src/core/envelope.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';

const cfg = resolveConfig({ siteKey: 'stk_test' });

describe('buildEnvelope branch coverage', () => {
  beforeEach(() => {
    resetSeq();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('includes ct when NetworkInformation API is available', () => {
    Object.defineProperty(navigator, 'connection', { value: { effectiveType: '4g' }, configurable: true });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.ct).toBe('4g');
    Object.defineProperty(navigator, 'connection', { value: undefined, configurable: true });
  });

  it('omits ct when NetworkInformation API is unavailable', () => {
    const desc = Object.getOwnPropertyDescriptor(navigator, 'connection');
    if (desc) Object.defineProperty(navigator, 'connection', { value: undefined, configurable: true });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.ct).toBeUndefined();
    if (desc) Object.defineProperty(navigator, 'connection', desc);
  });

  it('omits dpr when devicePixelRatio is 0 (falsy)', () => {
    const original = window.devicePixelRatio;
    Object.defineProperty(window, 'devicePixelRatio', { value: 0, configurable: true });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.dpr).toBeUndefined();
    Object.defineProperty(window, 'devicePixelRatio', { value: original, configurable: true });
  });

  it('preserves the FRACTIONAL devicePixelRatio (number, not rounded)', () => {
    Object.defineProperty(window, 'devicePixelRatio', { value: 2.625, configurable: true });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.dpr).toBe(2.625);
    Object.defineProperty(window, 'devicePixelRatio', { value: 1.5, configurable: true });
    expect(buildEnvelope(cfg, { name: 'pageview' }).dpr).toBe(1.5);
    Object.defineProperty(window, 'devicePixelRatio', { value: 1, configurable: true });
  });

  it('omits title when document.title is empty', () => {
    const original = document.title;
    document.title = '';
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.title).toBeUndefined();
    document.title = original;
  });

  it('includes properties when given a non-empty object', () => {
    const event = buildEnvelope(cfg, { name: 'custom', properties: { a: 1 } });
    expect(event.properties).toEqual({ a: 1 });
  });
});
