/** Event envelope. Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only. */
import type { BehavioralSignals, ResolvedConfig, StatflowEvent } from './config.js';
import { BROWSER } from './env.js';
import { nextSeq, uuid } from './ids.js';

declare const __TRACKER_VERSION__: string;
const TV = typeof __TRACKER_VERSION__ !== 'undefined' ? __TRACKER_VERSION__ : '0.1.0';

const PII = /[?&](email|token|key|secret|password|code|auth|access_token|id_token|refresh_token|session|ssn|dob)=[^&]*/gi;

export function sanitiseUrl(u: string): string {
  if (!u) return u;
  const base = BROWSER ? location.href : undefined;
  try {
    const x = new URL(u, base);
    x.username = x.password = '';
    return x.href.replace(PII, (m: string) => m.replace(/=.*/, `=${encodeURIComponent('[redacted]')}`));
  } catch { return u.replace(PII, (m: string) => m.replace(/=.*/, `=${encodeURIComponent('[redacted]')}`)); }
}

function parts(u: string): { pathname: string; hostname: string } {
  try { const x = new URL(u); return { pathname: x.pathname, hostname: x.hostname }; }
  catch { return { pathname: '', hostname: '' }; }
}

export interface EnvelopeInput {
  name: string;
  properties?: Record<string, unknown>;
  behavioral?: BehavioralSignals;
}

export function buildEnvelope(cfg: ResolvedConfig, input: EnvelopeInput): StatflowEvent {
  const w = BROWSER ? window : undefined;
  const n = BROWSER ? navigator : undefined;
  const url = sanitiseUrl(BROWSER ? location.href : '');
  const { pathname, hostname } = parts(url);
  const e: StatflowEvent = {
    eid: uuid(), k: cfg.siteKey,
    event: input.name, ts: Date.now(), seq: nextSeq(),
    url, pathname, hostname, tv: TV,
    vw: w?.innerWidth ?? 0, vh: w?.innerHeight ?? 0,
    sw: w?.screen?.width ?? 0, sh: w?.screen?.height ?? 0,
  };
  const ref = sanitiseUrl(BROWSER ? document.referrer : '');
  if (ref) e.ref = ref;
  const title = BROWSER ? document.title : '';
  if (title) e.title = title;
  const dpr = w?.devicePixelRatio;
  if (dpr) e.dpr = dpr;
  const ct = n && 'connection' in n ? (n as { connection?: { effectiveType?: string } }).connection?.effectiveType : undefined;
  if (ct) e.ct = ct;
  if (input.properties && Object.keys(input.properties).length) e.properties = input.properties;
  if (input.behavioral && Object.keys(input.behavioral).length) e.behavioral = input.behavioral;
  return e;
}
