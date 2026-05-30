/**
 * Statflow Tracker — Click, rage-click and dead-click collector.
 *
 * Behavioral signals (coordinates, element identity) ride the wire `b` block per
 * event-contract.md §5. Interaction modifiers and link metadata, which have no
 * behavioral mapping, ride `props`.
 *
 * Dead-click detection fences each click with a MutationObserver plus a
 * navigation/visibility check over `deadClickFenceMs`: if nothing in the DOM
 * changed, no navigation started, and the tab stayed put, the click is "dead".
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { BehavioralSignals, ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope, sanitiseUrl } from '../core/envelope.js';
import { ancestorHref, buildSelector, docPoint, elementBehavioral } from '../core/dom.js';

export { buildSelector };

type Push = (e: StatflowEvent) => void;

export interface ClickCollector { mount(p: Push): void; destroy(): void; }

export function createClickCollector(cfg: ResolvedConfig): ClickCollector {
  let h: ((e: MouseEvent) => void) | undefined;
  const rage = new Map<string, number[]>();
  const pendingFences = new Set<ReturnType<typeof setTimeout>>();
  let push: Push | undefined;

  function emitClick(t: Element, e: MouseEvent, b: BehavioralSignals): void {
    if (!push) return;
    const p: Record<string, unknown> = { button: e.button, shift: e.shiftKey, meta: e.metaKey, ctrl: e.ctrlKey };
    const link = ancestorHref(t); if (link) p['href'] = sanitiseUrl(link);
    const nm = t.getAttribute('name'); if (nm) p['attr_name'] = nm;
    push(buildEnvelope(cfg, { name: 'click', properties: p, behavioral: b }));
  }

  function detectRage(sel: string, now: number, b: BehavioralSignals): void {
    const buf = (rage.get(sel) ?? []).filter(ts => now - ts < cfg.rageWindowMs);
    buf.push(now); rage.set(sel, buf);
    if (buf.length >= 3 && push) {
      push(buildEnvelope(cfg, {
        name: 'rage_click',
        properties: { count: buf.length, duration_ms: (now - buf[0]!) | 0 },
        behavioral: { ...b, is_rage_click: true },
      }));
      rage.set(sel, []);
    }
  }

  function fenceDeadClick(b: BehavioralSignals): void {
    if (typeof MutationObserver === 'undefined') return;
    const startUrl = typeof location !== 'undefined' ? location.href : '';
    const startHidden = typeof document !== 'undefined' && document.visibilityState === 'hidden';
    let mutated = false;
    const mo = new MutationObserver(() => { mutated = true; mo.disconnect(); });
    mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, characterData: true });
    const id = setTimeout(() => {
      pendingFences.delete(id);
      mo.disconnect();
      const navigated = typeof location !== 'undefined' && location.href !== startUrl;
      const hidden = typeof document !== 'undefined' && document.visibilityState === 'hidden';
      if (!push || mutated || navigated || (hidden && !startHidden)) return;
      push(buildEnvelope(cfg, { name: 'dead_click', properties: { fence_ms: cfg.deadClickFenceMs }, behavioral: b }));
    }, cfg.deadClickFenceMs);
    pendingFences.add(id);
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined') return;
    push = p;
    h = (e: MouseEvent): void => {
      const t = e.target;
      if (!(t instanceof Element)) return;
      const pt = docPoint(e.clientX, e.clientY);
      const b = elementBehavioral(t, pt);
      emitClick(t, e, b);
      detectRage(b.element_selector!, e.timeStamp, b);
      fenceDeadClick(b);
    };
    window.addEventListener('click', h, { capture: true, passive: true });
  }

  function destroy(): void {
    if (typeof window !== 'undefined' && h) { window.removeEventListener('click', h, { capture: true }); h = undefined; }
    for (const id of pendingFences) clearTimeout(id);
    pendingFences.clear();
    rage.clear();
    push = undefined;
  }

  return { mount, destroy };
}
