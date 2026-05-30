/**
 * Statflow Tracker — Privacy-first, cookieless analytics.
 *
 * The static graph reachable from init() is deliberately minimal — pageview,
 * SPA and the transport/queue/envelope/config/privacy/ids machinery only — so
 * the core bundle is a single self-contained file with no behavioral collectors
 * in its graph. The heavier behavioral collectors (clicks, rage/dead clicks,
 * scroll, forms, engagement, visibility, vitals, errors) live in a SEPARATE
 * build artifact, `behavior.js`, fetched on demand the first time any behavioral
 * feature is enabled. The heatmap module is likewise a separate artifact fetched by
 * loadHeatmap(). Both follow the same URL-based import() pattern so the core
 * never statically references them (docs/tracker/tracker-design.md §7–§8).
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { CollectorName, ResolvedConfig, StatflowConfig, StatflowEvent } from './core/config.js';
import { BROWSER } from './core/env.js';
import { resolveConfig } from './core/config.js';
import { buildEnvelope } from './core/envelope.js';
import { EventQueue } from './core/queue.js';
import { createPageviewCollector } from './collectors/pageview.js';
import { createSpaCollector } from './collectors/spa.js';
import { evaluatePrivacy, evaluateSampleRate } from './collectors/privacy.js';
import { validateCustom } from './collectors/custom.js';

export type { StatflowConfig, StatflowEvent };

/** Collectors that ship in the lazily-loaded behavior bundle, not the core. */
const BEHAVIOR_COLLECTORS: readonly CollectorName[] = [
  'clicks', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors',
];

export interface StatflowPublic {
  page(): void;
  track(name: string, properties?: Record<string, unknown>): void;
  flush(): Promise<void>;
  destroy(): void;
  loadHeatmap(opts?: Record<string, unknown>): Promise<void>;
}

interface Instance {
  q: EventQueue;
  cfg: ResolvedConfig;
  teardowns: Array<() => void>;
  push(e: StatflowEvent): void;
  destroy(): void;
}

let _inst: Instance | undefined;

const safe = (fn: () => void): void => { try { fn(); } catch { /* tracker never throws into the host page */ } };

function _noop(): StatflowPublic {
  return { page() {}, track() {}, flush: () => Promise.resolve(), destroy() {}, loadHeatmap: () => Promise.resolve() };
}

function _api(inst: Instance): StatflowPublic {
  return {
    page() { safe(() => inst.push(buildEnvelope(inst.cfg, { name: 'pageview' }))); },
    track(n, p) {
      safe(() => {
        const v = validateCustom(n, p);
        if (v) inst.push(buildEnvelope(inst.cfg, { name: v.name, properties: v.props }));
      });
    },
    flush: () => inst.q.flush(false),
    destroy: () => inst.destroy(),
    loadHeatmap: (opts) => loadHeatmap(inst.cfg, opts),
  };
}

function moduleUrl(cfg: ResolvedConfig, file: string): string {
  const base = cfg.apiBase || (BROWSER ? window.location.origin : '');
  return new URL(file, base).href;
}

async function loadHeatmap(cfg: ResolvedConfig, opts?: Record<string, unknown>): Promise<void> {
  const url = moduleUrl(cfg, '/sf/tracker-heatmap.js');
  const mod = (await import(/* @vite-ignore */ url)) as { init?: (o?: Record<string, unknown>) => void };
  mod.init?.(opts);
}

/**
 * Fetches the separate behavior bundle by URL and mounts the enabled collectors
 * against the core queue, registering their teardown. Mirrors loadHeatmap so
 * the core never statically references the collectors.
 */
async function loadBehavior(inst: Instance): Promise<void> {
  const url = moduleUrl(inst.cfg, '/sf/behavior.js');
  const mod = (await import(/* @vite-ignore */ url)) as {
    mount?: (cfg: ResolvedConfig, push: (e: StatflowEvent) => void) => () => void;
  };
  if (!_inst || _inst !== inst || !mod.mount) return; // destroyed before the chunk resolved
  inst.teardowns.push(mod.mount(inst.cfg, inst.push));
}

export function init(raw: StatflowConfig): StatflowPublic {
  if (_inst) return _api(_inst);
  const cfg = resolveConfig(raw);
  if (evaluatePrivacy(cfg.dnt) === 'halt' || !evaluateSampleRate(cfg.sampleRate)) return _noop();
  const q = new EventQueue(cfg);
  const teardowns: Array<() => void> = [];
  const inst: Instance = {
    q, cfg, teardowns,
    push: (e: StatflowEvent) => q.push(e),
    destroy() { teardowns.forEach(f => safe(f)); q.destroy(); _inst = undefined; },
  };
  _inst = inst;

  if (!cfg.disable.includes('pageview')) safe(() => {
    const c = createPageviewCollector(cfg); inst.push(c.firePageview()); teardowns.push(() => c.destroy());
  });
  if (!cfg.disable.includes('spa')) safe(() => {
    const c = createSpaCollector(cfg); c.mount(inst.push); teardowns.push(() => c.destroy());
  });

  if (BEHAVIOR_COLLECTORS.some(c => !cfg.disable.includes(c))) {
    void loadBehavior(inst).catch(() => undefined);
  }

  q.start();
  return _api(inst);
}

function autoInit(): void {
  if (!BROWSER) return;
  const w = window as typeof window & { statflowConfig?: StatflowConfig; statflow?: { q?: IArguments[] } & Partial<StatflowPublic>; };
  const c = w.statflowConfig;
  if (!c || (!c.siteKey && !c.projectId)) return;
  const api = init(c);
  const q = w.statflow?.q ?? [];
  w.statflow = { ...api, q: [] };
  for (const a of q) { const [n, p] = Array.from(a) as [string, Record<string, unknown>?]; if (typeof n === 'string') api.track(n, p); }
}

if (BROWSER) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoInit, { once: true });
  else autoInit();
}
