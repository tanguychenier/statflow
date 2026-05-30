/**
 * Statflow Tracker — Shared DOM helpers: stable selector synthesis, text
 * scrubbing, and document-relative coordinate maths. Kept dependency-free and
 * tiny so every behavioral collector can reuse them.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { BehavioralSignals } from './config.js';

/** A value that looks like a UUID, long hex hash, or pure number — i.e. dynamic. */
function dyn(v: string): boolean {
  return /^[\da-f]{8}-[\da-f]{4}-/i.test(v) || /^[\da-f]{16,}$/i.test(v) || /^\d+$/.test(v);
}

/**
 * Shortest stable CSS selector for an element, walking up at most `maxDepth`
 * ancestors and stopping at the first stable anchor (`data-track-id`, stable
 * `id`). Dynamic id/class tokens are dropped to keep aggregation stable.
 */
export function buildSelector(el: Element, maxDepth = 5): string {
  const parts: string[] = [];
  let cur: Element | null = el;
  for (let d = 0; cur && cur !== document.documentElement && d < maxDepth; d++, cur = cur.parentElement) {
    const tid = cur.getAttribute('data-track-id');
    if (tid) { parts.unshift(`[data-track-id="${tid}"]`); break; }
    const id = cur.id;
    if (id && !dyn(id)) { parts.unshift(`${cur.tagName.toLowerCase()}#${id}`); break; }
    const cls = [...cur.classList].filter(c => !dyn(c)).slice(0, 2).join('.');
    parts.unshift(cls ? `${cur.tagName.toLowerCase()}.${cls}` : cur.tagName.toLowerCase());
  }
  return parts.join('>').slice(0, 256);
}

export function ancestorHref(el: Element): string | undefined {
  for (let c: Element | null = el; c; c = c.parentElement)
    if (c.tagName === 'A') return (c as HTMLAnchorElement).getAttribute('href') ?? undefined;
  return undefined;
}

/** Strip email-shaped tokens and cap length. Never transmits raw user text. */
export function scrubText(s: string, max = 128): string {
  return s.replace(/\S+@\S+\.\S+/g, '[redacted]').slice(0, max).trim();
}

export interface DocPoint { x: number; y: number; xp: number; yp: number; }

/** Document-relative coordinates + percentages (event-contract.md §5.1). */
export function docPoint(clientX: number, clientY: number): DocPoint {
  const sx = typeof window !== 'undefined' ? window.scrollX : 0;
  const sy = typeof window !== 'undefined' ? window.scrollY : 0;
  const body = typeof document !== 'undefined' ? document.body : undefined;
  const dw = body?.scrollWidth || 1, dh = body?.scrollHeight || 1;
  const x = (clientX + sx) | 0, y = (clientY + sy) | 0;
  return { x, y, xp: Math.round((x / dw) * 1e3) / 10, yp: Math.round((y / dh) * 1e3) / 10 };
}

/** Behavioral signal block (wire `b`) for a click-like interaction on `el`. */
export function elementBehavioral(el: Element, pt: DocPoint): BehavioralSignals {
  const b: BehavioralSignals = {
    click_x: pt.x, click_y: pt.y, click_x_pct: pt.xp, click_y_pct: pt.yp,
    element_tag: el.tagName.toLowerCase(),
    element_selector: buildSelector(el),
  };
  const txt = scrubText((el as HTMLElement).innerText ?? el.textContent ?? '');
  if (txt) b.element_text = txt;
  const id = el.getAttribute('id');
  if (id) b.element_id = id;
  return b;
}
