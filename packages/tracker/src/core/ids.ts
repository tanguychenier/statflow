/**
 * Statflow Tracker — Monotonic per-session seq and per-event UUIDv4.
 *
 * `seq` must be monotonic per SESSION, not per page load (event-contract.md §9):
 * two hard loads inside one session window must not restart the counter, or
 * their events collide and funnel/path reconstruction breaks. The counter is
 * therefore persisted in `sessionStorage` under a single integer key — no PII,
 * no cross-session identifier (sessionStorage is cleared when the tab/session
 * ends). When storage is unavailable (private mode, sandboxed iframe) it falls
 * back to an in-memory counter so the tracker never throws.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import { BROWSER } from './env.js';

const SEQ_KEY = 'sf_seq';
let mem = 0;

function store(): Storage | undefined {
  try {
    const s = BROWSER ? sessionStorage : undefined;
    if (!s) return undefined;
    const probe = '__sf_probe__';
    s.setItem(probe, '1');
    s.removeItem(probe);
    return s;
  } catch { return undefined; }
}

export function nextSeq(): number {
  const s = store();
  if (!s) return ++mem;
  try {
    const prev = parseInt(s.getItem(SEQ_KEY) ?? '', 10);
    const next = (Number.isFinite(prev) ? prev : 0) + 1;
    s.setItem(SEQ_KEY, String(next));
    return next;
  } catch { return ++mem; }
}

/** Test-only: clear the persisted and in-memory counter back to 0. */
export function resetSeq(): void {
  mem = 0;
  try { store()?.removeItem(SEQ_KEY); } catch { /* storage unavailable */ }
}

const HEX = '0123456789abcdef';

/**
 * RFC-4122 v4 UUID. Uses crypto.getRandomValues when available (every browser
 * the tracker targets), falling back to Math.random only for non-secure or
 * stripped-down environments so the tracker never throws on a missing API.
 */
export function uuid(): string {
  const c = BROWSER ? crypto : undefined;
  const b = new Uint8Array(16);
  if (c?.getRandomValues) c.getRandomValues(b);
  else for (let i = 0; i < 16; i++) b[i] = (Math.random() * 256) | 0;
  b[6] = (b[6]! & 0x0f) | 0x40;
  b[8] = (b[8]! & 0x3f) | 0x80;
  let out = '';
  for (let i = 0; i < 16; i++) {
    if (i === 4 || i === 6 || i === 8 || i === 10) out += '-';
    out += HEX[b[i]! >> 4]! + HEX[b[i]! & 0x0f]!;
  }
  return out;
}
