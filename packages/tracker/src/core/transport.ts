/**
 * Statflow Tracker — Transport layer.
 * sendBeacon → fetch keepalive → one retry after 2 s → drop.
 * text/plain avoids CORS preflight (beacon parity).
 * The body is the unified batch envelope (root key `events`, compact wire
 * format) defined in event-contract.md §3.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { StatflowEvent } from './config.js';
import { BROWSER } from './env.js';
import { toWire } from './wire.js';

declare const __TRACKER_VERSION__: string;
const TV = typeof __TRACKER_VERSION__ !== 'undefined' ? __TRACKER_VERSION__ : '0.1.0';

let _unloading = false;
export const markUnloading = (): void => { _unloading = true; };
export const isUnloading = (): boolean => _unloading;
export const resetUnloading = (): void => { _unloading = false; };

function serialise(events: StatflowEvent[]): string {
  return JSON.stringify({ events: events.map(toWire), sent_at: Date.now(), sdk: 'tracker', sdk_v: TV });
}

export async function sendBatch(events: StatflowEvent[], url: string, retry = true): Promise<void> {
  if (!events.length) return;
  const body = serialise(events);

  if (_unloading && BROWSER && navigator.sendBeacon) {
    if (navigator.sendBeacon(url, body)) return;
  }

  try {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'text/plain' }, body, keepalive: true });
    if (!r.ok && r.status >= 500 && retry) await _retry(events, url);
  } catch {
    if (retry) await _retry(events, url);
  }
}

async function _retry(events: StatflowEvent[], url: string): Promise<void> {
  await new Promise<void>(res => setTimeout(res, 2000));
  await sendBatch(events, url, false);
}
