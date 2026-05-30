/**
 * Branch-coverage tests for the pageview collector.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPageviewCollector } from '../../src/collectors/pageview.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';

const config = resolveConfig({ siteKey: 'stk_pv_branch' });

describe('pageview collector — branch coverage', () => {
  beforeEach(() => {
    resetSeq();
    vi.restoreAllMocks();
  });

  it('includes load_time_ms when timing data is positive', () => {
    // Mock performance.timing to return positive load time
    const mockTiming = {
      navigationStart: 1000,
      loadEventEnd: 1500,
    };
    Object.defineProperty(performance, 'timing', {
      get: () => mockTiming,
      configurable: true,
    });

    const collector = createPageviewCollector(config);
    const event = collector.firePageview();

    expect(event.properties?.['load_time_ms']).toBe(500);
  });

  it('omits load_time_ms when loadEventEnd is 0 (page not fully loaded)', () => {
    const mockTiming = {
      navigationStart: 1000,
      loadEventEnd: 0,
    };
    Object.defineProperty(performance, 'timing', {
      get: () => mockTiming,
      configurable: true,
    });

    const collector = createPageviewCollector(config);
    const event = collector.firePageview();

    expect(event.properties?.['load_time_ms']).toBeUndefined();
  });

  it('includes entry_type from navigation timing entry when available', () => {
    // Mock getEntriesByType to return a navigation entry
    vi.spyOn(performance, 'getEntriesByType').mockReturnValue([
      { type: 'reload' } as PerformanceNavigationTiming,
    ]);

    const collector = createPageviewCollector(config);
    const event = collector.firePageview();

    expect(event.properties?.['entry_type']).toBe('reload');
  });
});
