/**
 * Statflow Tracker — DNT / GPC privacy signal evaluation.
 *
 * The only non-tracking mode is `disable` (ADR-0008 §5, privacy.md §5): when a
 * Do-Not-Track or Global Privacy Control signal is present the tracker does not
 * initialise. The previously described `anonymous` mode is gone — `visitor_id`
 * and `session_id` are server-derived, so there is nothing the client could
 * anonymise. Sites choose `ignore` or `disable` only.
 *
 * Every DNT spelling browsers have shipped is honoured: `navigator.doNotTrack`
 * ('1'/'yes'), the legacy `window.doNotTrack` and `navigator.msDoNotTrack`, and
 * the standardised `navigator.globalPrivacyControl` (GPC).
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { DntBehaviour } from '../core/config.js';
import { BROWSER } from '../core/env.js';

export type PrivacyDecision = 'proceed' | 'halt';

function dntSignal(): boolean {
  const n = BROWSER
    ? (navigator as Navigator & { msDoNotTrack?: string | null; globalPrivacyControl?: unknown })
    : undefined;
  const w = BROWSER
    ? (window as Window & { doNotTrack?: string | null })
    : undefined;
  const dnt = n?.doNotTrack ?? n?.msDoNotTrack ?? w?.doNotTrack ?? null;
  if (dnt === '1' || dnt === 'yes') return true;
  return n?.globalPrivacyControl === true;
}

export function evaluatePrivacy(dnt: DntBehaviour): PrivacyDecision {
  if (dnt === 'ignore') return 'proceed';
  return dntSignal() ? 'halt' : 'proceed';
}

export function evaluateSampleRate(rate: number): boolean {
  if (rate >= 1) return true;
  if (rate <= 0) return false;
  return Math.random() < rate;
}
