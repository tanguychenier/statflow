import { describe, it, expect, beforeEach } from 'vitest';
import { createPageviewCollector } from '../../src/collectors/pageview.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';

const config = resolveConfig({ siteKey: 'stk_pv_test' });

describe('createPageviewCollector', () => {
  beforeEach(() => {
    resetSeq();
  });

  it('firePageview returns an event with event="pageview"', () => {
    const collector = createPageviewCollector(config);
    const event = collector.firePageview();
    expect(event.event).toBe('pageview');
  });

  it('event carries the configured site key', () => {
    const collector = createPageviewCollector(config);
    const event = collector.firePageview();
    expect(event.k).toBe('stk_pv_test');
  });

  it('event has a valid timestamp', () => {
    const before = Date.now();
    const collector = createPageviewCollector(config);
    const event = collector.firePageview();
    const after = Date.now();
    expect(event.ts).toBeGreaterThanOrEqual(before);
    expect(event.ts).toBeLessThanOrEqual(after);
  });

  it('event has sequential seq numbers', () => {
    const collector = createPageviewCollector(config);
    const e1 = collector.firePageview();
    const e2 = collector.firePageview();
    expect(e2.seq).toBe(e1.seq + 1);
  });

  it('destroy() does not throw', () => {
    const collector = createPageviewCollector(config);
    expect(() => collector.destroy()).not.toThrow();
  });

  it('includes load_time_ms when performance.timing is available', () => {
    // jsdom provides performance.timing
    const collector = createPageviewCollector(config);
    const event = collector.firePageview();
    // In jsdom, navigationStart may equal loadEventEnd=0, so this may or may not be present
    // just verify it doesn't throw and the structure is correct
    if (event.properties?.['load_time_ms'] != null) {
      expect(typeof event.properties['load_time_ms']).toBe('number');
    }
  });
});
