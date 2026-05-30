import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createErrorsCollector, scrub } from '../../src/collectors/errors.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('scrub', () => {
  it('redacts UUIDs', () => {
    expect(scrub('id a1b2c3d4-e5f6-7890-abcd-ef1234567890 end')).toBe('id [redacted] end');
  });

  it('redacts JWTs', () => {
    expect(scrub('token eyJhbGc.eyJzdWI.sig here')).toContain('[redacted]');
  });

  it('redacts emails', () => {
    expect(scrub('to user@example.com now')).toBe('to [redacted] now');
  });

  it('redacts a 16-digit card in 4x4 grouped form (space-separated)', () => {
    expect(scrub('card 4111 1111 1111 1111 ok')).toBe('card [redacted] ok');
  });

  it('redacts a 16-digit card in 4x4 grouped form (hyphen-separated)', () => {
    expect(scrub('pan 4111-1111-1111-1111 done')).toBe('pan [redacted] done');
  });

  it('redacts a contiguous 15- or 16-digit PAN', () => {
    expect(scrub('num 4111111111111111 x')).toBe('num [redacted] x');
    expect(scrub('amex 378282246310005 x')).toBe('amex [redacted] x');
  });

  it('redacts an Amex 4-6-5 grouped number', () => {
    expect(scrub('amex 3782 822463 10005 x')).toBe('amex [redacted] x');
  });

  it('does NOT redact a 13-digit order/phone number (regex tightened — finding #10)', () => {
    expect(scrub('order 1234567890123 shipped')).toBe('order 1234567890123 shipped');
    expect(scrub('call +1 415 555 0100 now')).toBe('call +1 415 555 0100 now');
  });

  it('does NOT redact mixed-separator digit blobs that are not card-shaped', () => {
    // Inconsistent separators must not match the grouped-card alternation.
    expect(scrub('ref 4111-1111 1111-1111 ok')).toBe('ref 4111-1111 1111-1111 ok');
  });
});

describe('createErrorsCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createErrorsCollector>;

  beforeEach(() => {
    resetSeq();
    collected = [];
    collector = createErrorsCollector(resolveConfig({ siteKey: 'stk_err' }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
  });

  it('captures an unhandled error with structured fields', () => {
    const err = new Error('Cannot read price');
    window.dispatchEvent(new ErrorEvent('error', {
      message: 'Cannot read price', filename: 'https://x/app.js?v=1', lineno: 412, colno: 18, error: err,
    }));

    const ev = collected.find((e) => e.event === 'js_error')!;
    expect(ev).toBeDefined();
    expect(ev.properties?.['type']).toBe('error');
    expect(ev.properties?.['message']).toBe('Cannot read price');
    expect(ev.properties?.['source']).toBe('https://x/app.js'); // query stripped
    expect(ev.properties?.['lineno']).toBe(412);
    expect(ev.properties?.['colno']).toBe(18);
    expect(ev.properties?.['count']).toBe(1);
    expect(typeof ev.properties?.['stack']).toBe('string');
  });

  it('captures an unhandled promise rejection', () => {
    const event = new Event('unhandledrejection') as PromiseRejectionEvent;
    Object.defineProperty(event, 'reason', { value: new Error('boom'), configurable: true });
    window.dispatchEvent(event);

    const ev = collected.find((e) => e.event === 'js_error')!;
    expect(ev.properties?.['type']).toBe('unhandledrejection');
    expect(ev.properties?.['message']).toBe('boom');
  });

  it('handles a non-Error rejection reason', () => {
    const event = new Event('unhandledrejection') as PromiseRejectionEvent;
    Object.defineProperty(event, 'reason', { value: 'string failure', configurable: true });
    window.dispatchEvent(event);
    expect(collected.find((e) => e.event === 'js_error')!.properties?.['message']).toBe('string failure');
  });

  it('scrubs PII from the message before transmission', () => {
    window.dispatchEvent(new ErrorEvent('error', { message: 'failed for user@example.com', filename: 'a.js', lineno: 1, colno: 1 }));
    const ev = collected.find((e) => e.event === 'js_error')!;
    expect(ev.properties?.['message']).not.toContain('user@example.com');
    expect(ev.properties?.['message']).toContain('[redacted]');
  });

  it('deduplicates identical errors within a page view', () => {
    const fire = (): void => {
      window.dispatchEvent(new ErrorEvent('error', { message: 'same', filename: 'a.js', lineno: 5, colno: 1 }));
    };
    fire(); fire(); fire();
    expect(collected.filter((e) => e.event === 'js_error')).toHaveLength(1);
  });

  it('treats errors with different locations as distinct', () => {
    window.dispatchEvent(new ErrorEvent('error', { message: 'x', filename: 'a.js', lineno: 1, colno: 1 }));
    window.dispatchEvent(new ErrorEvent('error', { message: 'x', filename: 'a.js', lineno: 2, colno: 1 }));
    expect(collected.filter((e) => e.event === 'js_error')).toHaveLength(2);
  });

  it('truncates long messages to 512 chars', () => {
    window.dispatchEvent(new ErrorEvent('error', { message: 'x'.repeat(2000), filename: 'a.js', lineno: 1, colno: 1 }));
    expect((collected.find((e) => e.event === 'js_error')!.properties?.['message'] as string).length).toBeLessThanOrEqual(512);
  });

  it('destroy() detaches the error listeners', () => {
    collector.destroy();
    window.dispatchEvent(new ErrorEvent('error', { message: 'after', filename: 'a.js', lineno: 1, colno: 1 }));
    expect(collected.find((e) => e.event === 'js_error')).toBeUndefined();
  });
});
