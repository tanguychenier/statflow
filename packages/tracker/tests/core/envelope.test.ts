import { describe, it, expect, beforeEach } from 'vitest';
import { buildEnvelope, sanitiseUrl } from '../../src/core/envelope.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';

const cfg = resolveConfig({ siteKey: 'stk_test' });

describe('sanitiseUrl', () => {
  it('strips credentials from a URL', () => {
    expect(sanitiseUrl('https://user:pass@example.com/path')).toBe('https://example.com/path');
  });

  it('redacts known PII query parameters', () => {
    const result = sanitiseUrl('https://example.com/callback?code=abc123&foo=bar');
    expect(result).toMatch(/code=%5Bredacted%5D|code=\[redacted\]/i);
    expect(result).toContain('foo=bar');
    expect(result).not.toContain('abc123');
  });

  it('resolves a bare relative token against the current origin (so relative hrefs are still sanitisable)', () => {
    expect(sanitiseUrl('not-a-url')).toBe('http://localhost:3000/not-a-url');
  });

  it('retains hash fragments', () => {
    expect(sanitiseUrl('https://example.com/page#section-2')).toContain('#section-2');
  });

  it('handles URLs with no query string gracefully', () => {
    expect(sanitiseUrl('https://example.com/about')).toBe('https://example.com/about');
  });

  it('redacts email PII param', () => {
    const result = sanitiseUrl('https://example.com/?email=foo@bar.com&safe=yes');
    expect(result).toMatch(/email=%5Bredacted%5D|email=\[redacted\]/i);
    expect(result).toContain('safe=yes');
    expect(result).not.toContain('foo@bar.com');
  });

  it('resolves and redacts a RELATIVE URL (e.g. an anchor href) against the current origin', () => {
    const result = sanitiseUrl('/checkout?token=secret123&page=2');
    expect(result).toMatch(/token=%5Bredacted%5D|token=\[redacted\]/i);
    expect(result).toContain('page=2');
    expect(result).not.toContain('secret123');
  });

  it('still redacts PII even when the value is not a parseable URL at all', () => {
    const result = sanitiseUrl('javascript:void(0)?access_token=leak');
    expect(result).not.toContain('leak');
  });

  it('returns the empty string unchanged', () => {
    expect(sanitiseUrl('')).toBe('');
  });
});

describe('buildEnvelope', () => {
  beforeEach(() => {
    resetSeq();
  });

  it('produces an event with all mandatory fields', () => {
    const event = buildEnvelope(cfg, { name: 'pageview' });

    expect(event.event).toBe('pageview');
    expect(typeof event.eid).toBe('string');
    expect(event.eid).toMatch(/^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i);
    expect(event.k).toBe('stk_test');
    expect(typeof event.ts).toBe('number');
    expect(event.ts).toBeGreaterThan(0);
    expect(event.seq).toBe(1);
    expect(typeof event.pathname).toBe('string');
    expect(typeof event.hostname).toBe('string');
    expect(typeof event.vw).toBe('number');
    expect(typeof event.vh).toBe('number');
    expect(typeof event.sw).toBe('number');
    expect(typeof event.sh).toBe('number');
    expect(typeof event.tv).toBe('string');
  });

  it('derives pathname and hostname from the current URL', () => {
    const event = buildEnvelope(cfg, { name: 'pageview' });
    const parsed = new URL(event.url);
    expect(event.pathname).toBe(parsed.pathname);
    expect(event.hostname).toBe(parsed.hostname);
  });

  it('generates a fresh event_id per event', () => {
    const a = buildEnvelope(cfg, { name: 'pageview' });
    const b = buildEnvelope(cfg, { name: 'pageview' });
    expect(a.eid).not.toBe(b.eid);
  });

  it('increments seq on each call', () => {
    const e1 = buildEnvelope(cfg, { name: 'pageview' });
    const e2 = buildEnvelope(cfg, { name: 'click' });
    expect(e1.seq).toBe(1);
    expect(e2.seq).toBe(2);
  });

  it('includes properties when provided', () => {
    const event = buildEnvelope(cfg, { name: 'custom', properties: { a: 1 } });
    expect(event.properties).toEqual({ a: 1 });
  });

  it('omits properties field when not provided', () => {
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.properties).toBeUndefined();
  });

  it('omits properties field when empty object is provided', () => {
    const event = buildEnvelope(cfg, { name: 'pageview', properties: {} });
    expect(event.properties).toBeUndefined();
  });

  it('includes behavioral signals when provided', () => {
    const event = buildEnvelope(cfg, { name: 'click', behavioral: { click_x: 10, click_y: 20 } });
    expect(event.behavioral).toEqual({ click_x: 10, click_y: 20 });
  });

  it('omits behavioral when empty', () => {
    const event = buildEnvelope(cfg, { name: 'click', behavioral: {} });
    expect(event.behavioral).toBeUndefined();
  });

  it('includes referrer when document.referrer is non-empty', () => {
    Object.defineProperty(document, 'referrer', { value: 'https://google.com/', configurable: true });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.ref).toBe('https://google.com/');
    Object.defineProperty(document, 'referrer', { value: '', configurable: true });
  });

  it('sanitises the referrer: strips credentials and redacts PII query params', () => {
    Object.defineProperty(document, 'referrer', {
      value: 'https://user:pass@example.com/p?token=secret&ref=campaign',
      configurable: true,
    });
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.ref).not.toContain('user:pass');
    expect(event.ref).not.toContain('secret');
    expect(event.ref).toContain('example.com');
    expect(event.ref).toContain('ref=campaign');
    Object.defineProperty(document, 'referrer', { value: '', configurable: true });
  });

  it('includes page title when set', () => {
    document.title = 'Test Page';
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(event.title).toBe('Test Page');
  });

  it('does NOT carry a user agent on the wire (server reads it from the header)', () => {
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect((event as Record<string, unknown>)['ua']).toBeUndefined();
  });

  it('sanitises the current URL', () => {
    const event = buildEnvelope(cfg, { name: 'pageview' });
    expect(() => new URL(event.url)).not.toThrow();
  });
});
