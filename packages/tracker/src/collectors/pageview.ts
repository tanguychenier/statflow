/**
 * Statflow Tracker — Pageview collector.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { BROWSER } from '../core/env.js';
import { buildEnvelope } from '../core/envelope.js';

export interface PageviewCollector {
  firePageview(): StatflowEvent;
  destroy(): void;
}

export function createPageviewCollector(config: ResolvedConfig): PageviewCollector {
  function firePageview(): StatflowEvent {
    const props: Record<string, unknown> = {};
    if (BROWSER && typeof performance !== 'undefined') {
      const t = performance.timing;
      if (t != null) {
        const ms = t.loadEventEnd - t.navigationStart;
        if (ms > 0) props['load_time_ms'] = ms;
      }
      const entries = performance.getEntriesByType?.('navigation') as PerformanceNavigationTiming[] | undefined;
      const nav = entries?.[0];
      if (nav?.type) props['entry_type'] = nav.type;
    }
    const input = Object.keys(props).length > 0 ? { name: 'pageview', properties: props } : { name: 'pageview' };
    return buildEnvelope(config, input);
  }

  return { firePageview, destroy(): void {} };
}
