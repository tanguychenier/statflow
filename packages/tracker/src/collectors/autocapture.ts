/**
 * Statflow Tracker — Heavier behavioral collectors, bundled behind a dynamic
 * import() boundary.
 *
 * The core entry (pageview + SPA + transport) must stay under 2 KB gzipped
 * (docs/tracker/tracker-design.md §7). Pulling clicks, scroll, forms,
 * engagement, visibility, vitals and errors into this module — reached only via
 * import() from index.ts — keeps them in a separate chunk that never inflates
 * the core bundle, while still shipping the full autocapture experience by
 * default.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { CollectorName, ResolvedConfig, StatflowEvent } from '../core/config.js';
import { createClickCollector } from './clicks.js';
import { createScrollCollector } from './scroll.js';
import { createFormsCollector } from './forms.js';
import { createEngagementCollector } from './engagement.js';
import { createVisibilityCollector } from './visibility.js';
import { createVitalsCollector } from './vitals.js';
import { createErrorsCollector } from './errors.js';

interface Pushable { mount(p: (e: StatflowEvent) => void): void; destroy(): void; }

const REGISTRY: Array<[CollectorName, (cfg: ResolvedConfig) => Pushable]> = [
  ['clicks', createClickCollector],
  ['scroll', createScrollCollector],
  ['forms', createFormsCollector],
  ['engagement', createEngagementCollector],
  ['visibility', createVisibilityCollector],
  ['vitals', createVitalsCollector],
  ['errors', createErrorsCollector],
];

/**
 * Mounts every enabled behavioral collector and returns a single teardown that
 * detaches all of them. Each collector is isolated, so one throwing never stops
 * the others from mounting.
 */
export function mountAutocapture(cfg: ResolvedConfig, push: (e: StatflowEvent) => void): () => void {
  const teardowns: Array<() => void> = [];
  for (const [name, factory] of REGISTRY) {
    if (cfg.disable.includes(name)) continue;
    try {
      const c = factory(cfg);
      c.mount(push);
      teardowns.push(() => c.destroy());
    } catch { /* a failing collector must never block the others */ }
  }
  return () => {
    for (const t of teardowns) { try { t(); } catch { /* idempotent teardown */ } }
  };
}
