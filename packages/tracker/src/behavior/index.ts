/**
 * Statflow Tracker — Behavioral collectors (lazily-loaded entry point).
 *
 * This is a SEPARATE tsup bundle (`behavior.js`), built exactly like the
 * heatmap module and likewise never imported by the core `src/index.ts`. The
 * core's static graph stays minimal (pageview + SPA + transport); this bundle —
 * carrying clicks, rage/dead clicks, scroll, forms, engagement, visibility, Web
 * Vitals and JS errors — is fetched on demand via import() the first time any
 * behavioral feature is enabled (docs/tracker/tracker-design.md §7).
 *
 * It bundles its own copy of the few shared core helpers (envelope, wire, ids,
 * DOM utilities). That duplication is deliberate: it removes the synchronous
 * shared chunk that in-graph splitting would otherwise force onto the core's
 * critical path. seq stays monotonic per session because the counter lives in
 * sessionStorage (core/ids.ts), and events flow through the core queue via the
 * `push` callback so batching and ordering are preserved.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { mountAutocapture } from '../collectors/autocapture.js';

export type { ResolvedConfig, StatflowEvent };

/**
 * Mounts every enabled behavioral collector against the core's `push` and the
 * already-resolved config. Returns a single teardown that detaches all of them.
 */
export function mount(cfg: ResolvedConfig, push: (e: StatflowEvent) => void): () => void {
  return mountAutocapture(cfg, push);
}
