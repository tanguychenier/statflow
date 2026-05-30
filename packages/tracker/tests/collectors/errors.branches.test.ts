import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createErrorsCollector } from '../../src/collectors/errors.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('errors collector — branch coverage', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createErrorsCollector>;

  beforeEach(() => {
    resetSeq();
    collected = [];
    collector = createErrorsCollector(resolveConfig({ siteKey: 'stk_err_b' }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
  });

  it('handles an error event with no message, filename, or position', () => {
    window.dispatchEvent(new ErrorEvent('error', {}));
    const ev = collected.find((e) => e.event === 'js_error')!;
    expect(ev.properties?.['message']).toBe('');
    expect(ev.properties?.['source']).toBe('');
    expect(ev.properties?.['lineno']).toBe(0);
    expect(ev.properties?.['colno']).toBe(0);
    expect(ev.properties?.['stack']).toBeUndefined();
  });

  it('includes a scrubbed stack when the error carries one', () => {
    const err = new Error('with stack');
    err.stack = 'Error: with stack token a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    window.dispatchEvent(new ErrorEvent('error', { message: 'with stack', filename: 'a.js', lineno: 1, colno: 1, error: err }));
    const ev = collected.find((e) => e.event === 'js_error')!;
    expect(ev.properties?.['stack']).toContain('[redacted]');
  });

  it('captures a rejection stack and handles a nullish reason', () => {
    const withStack = new Event('unhandledrejection') as PromiseRejectionEvent;
    const err = new Error('async fail');
    Object.defineProperty(withStack, 'reason', { value: err, configurable: true });
    window.dispatchEvent(withStack);
    expect(collected.find((e) => e.properties?.['type'] === 'unhandledrejection')!.properties?.['stack']).toBeDefined();

    const nullReason = new Event('unhandledrejection') as PromiseRejectionEvent;
    Object.defineProperty(nullReason, 'reason', { value: undefined, configurable: true });
    window.dispatchEvent(nullReason);
    expect(collected.filter((e) => e.properties?.['type'] === 'unhandledrejection').length).toBeGreaterThanOrEqual(1);
  });

  it('truncates an over-long stack to 2000 chars', () => {
    const err = new Error('big');
    err.stack = 'x'.repeat(5000);
    window.dispatchEvent(new ErrorEvent('error', { message: 'big', filename: 'a.js', lineno: 1, colno: 1, error: err }));
    expect((collected.find((e) => e.event === 'js_error')!.properties?.['stack'] as string).length).toBeLessThanOrEqual(2000);
  });
});
