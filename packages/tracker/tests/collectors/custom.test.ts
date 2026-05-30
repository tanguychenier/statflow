import { describe, it, expect } from 'vitest';
import { validateCustom } from '../../src/collectors/custom.js';

describe('validateCustom', () => {
  it('accepts a snake_case name with no properties', () => {
    expect(validateCustom('video_play')).toEqual({ name: 'video_play', props: {} });
  });

  it('accepts scalar properties', () => {
    const v = validateCustom('purchase', { revenue: 12.5, currency: 'EUR', repeat: true });
    expect(v).toEqual({ name: 'purchase', props: { revenue: 12.5, currency: 'EUR', repeat: true } });
  });

  it('rejects a name with uppercase or spaces', () => {
    expect(validateCustom('VideoPlay')).toBeNull();
    expect(validateCustom('video play')).toBeNull();
  });

  it('rejects the reserved sf_ prefix', () => {
    expect(validateCustom('sf_internal')).toBeNull();
  });

  it('rejects a name that does not start with a letter', () => {
    expect(validateCustom('1abc')).toBeNull();
    expect(validateCustom('_abc')).toBeNull();
  });

  it('rejects a name longer than 64 chars', () => {
    expect(validateCustom('a'.repeat(65))).toBeNull();
    expect(validateCustom('a'.repeat(64))).not.toBeNull();
  });

  it('rejects non-string names', () => {
    expect(validateCustom(42 as unknown as string)).toBeNull();
  });

  it('rejects more than 20 properties', () => {
    const props: Record<string, number> = {};
    for (let i = 0; i < 21; i++) props[`k${i}`] = i;
    expect(validateCustom('many', props)).toBeNull();
  });

  it('accepts exactly 20 properties', () => {
    const props: Record<string, number> = {};
    for (let i = 0; i < 20; i++) props[`k${i}`] = i;
    expect(validateCustom('twenty', props)).not.toBeNull();
  });

  it('rejects invalid property keys', () => {
    expect(validateCustom('e', { 'Bad-Key': 1 })).toBeNull();
    expect(validateCustom('e', { '': 1 })).toBeNull();
  });

  it('rejects nested objects and arrays', () => {
    expect(validateCustom('e', { nested: { a: 1 } as unknown as string })).toBeNull();
    expect(validateCustom('e', { arr: [1, 2] as unknown as string })).toBeNull();
  });

  it('rejects non-finite numbers', () => {
    expect(validateCustom('e', { n: NaN })).toBeNull();
    expect(validateCustom('e', { n: Infinity })).toBeNull();
  });

  it('rejects null / undefined values', () => {
    expect(validateCustom('e', { n: null as unknown as string })).toBeNull();
    expect(validateCustom('e', { n: undefined as unknown as string })).toBeNull();
  });

  it('rejects a payload exceeding the 4 KB serialised ceiling', () => {
    expect(validateCustom('big', { blob: 'x'.repeat(5000) })).toBeNull();
  });
});
