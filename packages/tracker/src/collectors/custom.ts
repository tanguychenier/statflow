/**
 * Statflow Tracker — Custom-event validation for statflow.track().
 *
 * Enforces the event-contract.md §6 constraints client-side so malformed events
 * never reach the wire: snake_case name (reserved `sf_` prefix rejected), at most
 * 20 flat properties, scalar values only (string/number/boolean), and a 4 KB
 * serialised ceiling. Invalid input yields `null` (the event is dropped) so the
 * tracker never throws into the host page.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

const NAME = /^[a-z][a-z0-9_]{0,63}$/;
const KEY = /^[a-z0-9_]{1,50}$/;
const MAX_KEYS = 20;
const MAX_BYTES = 4096;

export interface CustomPayload { name: string; props: Record<string, string | number | boolean>; }

function isScalar(v: unknown): v is string | number | boolean {
  return typeof v === 'string' || (typeof v === 'number' && Number.isFinite(v)) || typeof v === 'boolean';
}

export function validateCustom(name: string, properties?: Record<string, unknown>): CustomPayload | null {
  if (typeof name !== 'string' || !NAME.test(name) || name.startsWith('sf_')) return null;
  const props: Record<string, string | number | boolean> = {};
  if (properties) {
    const keys = Object.keys(properties);
    if (keys.length > MAX_KEYS) return null;
    for (const k of keys) {
      const v = properties[k];
      if (!KEY.test(k) || !isScalar(v)) return null;
      props[k] = v;
    }
  }
  try { if (JSON.stringify(props).length > MAX_BYTES) return null; } catch { return null; }
  return { name, props };
}
