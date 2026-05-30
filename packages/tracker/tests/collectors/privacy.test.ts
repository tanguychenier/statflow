import { describe, it, expect, vi, afterEach } from 'vitest';
import { evaluatePrivacy, evaluateSampleRate } from '../../src/collectors/privacy.js';

function setNav(prop: string, value: unknown): void {
  Object.defineProperty(navigator, prop, { value, configurable: true });
}

function clearNav(prop: string): void {
  Object.defineProperty(navigator, prop, { value: undefined, configurable: true });
}

describe('evaluatePrivacy', () => {
  afterEach(() => {
    setNav('doNotTrack', null);
    clearNav('msDoNotTrack');
    clearNav('globalPrivacyControl');
    Object.defineProperty(window, 'doNotTrack', { value: undefined, configurable: true });
    vi.restoreAllMocks();
  });

  it("returns 'proceed' when dnt is 'ignore' regardless of navigator signals", () => {
    setNav('doNotTrack', '1');
    expect(evaluatePrivacy('ignore')).toBe('proceed');
  });

  it("returns 'proceed' when no DNT/GPC signal is active", () => {
    setNav('doNotTrack', null);
    expect(evaluatePrivacy('disable')).toBe('proceed');
  });

  it("returns 'halt' when navigator.doNotTrack === '1' and dnt='disable'", () => {
    setNav('doNotTrack', '1');
    expect(evaluatePrivacy('disable')).toBe('halt');
  });

  it("honours navigator.doNotTrack === 'yes' (legacy spelling)", () => {
    setNav('doNotTrack', 'yes');
    expect(evaluatePrivacy('disable')).toBe('halt');
  });

  it('honours navigator.msDoNotTrack', () => {
    setNav('doNotTrack', null);
    setNav('msDoNotTrack', '1');
    expect(evaluatePrivacy('disable')).toBe('halt');
  });

  it('honours legacy window.doNotTrack', () => {
    setNav('doNotTrack', null);
    Object.defineProperty(window, 'doNotTrack', { value: 'yes', configurable: true });
    expect(evaluatePrivacy('disable')).toBe('halt');
  });

  it("returns 'halt' when GPC=true even if DNT is unset", () => {
    setNav('doNotTrack', null);
    setNav('globalPrivacyControl', true);
    expect(evaluatePrivacy('disable')).toBe('halt');
  });

  it("ignores GPC values that are not strictly true", () => {
    setNav('doNotTrack', null);
    setNav('globalPrivacyControl', 'true');
    expect(evaluatePrivacy('disable')).toBe('proceed');
  });

  it("returns 'proceed' when DNT='0' (explicitly not set)", () => {
    setNav('doNotTrack', '0');
    expect(evaluatePrivacy('disable')).toBe('proceed');
  });
});

describe('evaluateSampleRate', () => {
  it('always returns true for sampleRate=1', () => {
    for (let i = 0; i < 20; i++) {
      expect(evaluateSampleRate(1)).toBe(true);
    }
  });

  it('always returns false for sampleRate=0', () => {
    for (let i = 0; i < 20; i++) {
      expect(evaluateSampleRate(0)).toBe(false);
    }
  });

  it('returns true for sampleRate > 1 (treated as 100%)', () => {
    expect(evaluateSampleRate(2)).toBe(true);
  });

  it('uses Math.random() for fractional rates', () => {
    vi.spyOn(Math, 'random').mockReturnValue(0.3);
    expect(evaluateSampleRate(0.5)).toBe(true); // 0.3 < 0.5

    vi.spyOn(Math, 'random').mockReturnValue(0.8);
    expect(evaluateSampleRate(0.5)).toBe(false); // 0.8 >= 0.5
  });
});
