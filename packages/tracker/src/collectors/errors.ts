/**
 * Statflow Tracker — JavaScript error collector.
 *
 * Captures unhandled errors (`error` event) and unhandled promise rejections.
 * Identical errors (message+source+line) within one page view are coalesced via
 * a `count`. Messages and stacks are scrubbed of UUID/JWT/email/credit-card
 * tokens, and source URLs have their query strings stripped, before transmission.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope } from '../core/envelope.js';

type Push = (e: StatflowEvent) => void;

const UUID = /\b[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}\b/gi;
const JWT = /\beyJ[\w-]+\.[\w-]+\.[\w-]+\b/g;
const EMAIL = /\b[\w.+-]+@[\w-]+\.[\w.-]+\b/g;
// Card-shaped sequences only: either four 4-digit groups joined by a single,
// consistent space or hyphen (Visa/Mastercard layout), or an Amex 4-6-5 group
// layout, or a contiguous 15-16 digit run. A plain 13-digit blob (phone/order
// numbers) is no longer swept up.
const CARD = /\b\d{4}([ -])\d{4}\1\d{4}\1\d{4}\b|\b\d{4}([ -])\d{6}\2\d{5}\b|\b\d{15,16}\b/g;

export function scrub(s: string): string {
  return s.replace(UUID, '[redacted]').replace(JWT, '[redacted]').replace(EMAIL, '[redacted]').replace(CARD, '[redacted]');
}

function stripQuery(url: string): string {
  const q = url.indexOf('?');
  return q === -1 ? url : url.slice(0, q);
}

export interface ErrorsCollector { mount(p: Push): void; destroy(): void; }

export function createErrorsCollector(cfg: ResolvedConfig): ErrorsCollector {
  let push: Push | undefined;
  let onError: ((e: ErrorEvent) => void) | undefined;
  let onRejection: ((e: PromiseRejectionEvent) => void) | undefined;
  const seen = new Map<string, number>();

  function emit(props: Record<string, unknown>, key: string): void {
    if (!push) return;
    const prior = seen.get(key);
    if (prior !== undefined) { seen.set(key, prior + 1); return; }
    seen.set(key, 1);
    push(buildEnvelope(cfg, { name: 'js_error', properties: { ...props, count: 1 } }));
  }

  function handleError(e: ErrorEvent): void {
    const message = scrub(String(e.message ?? '')).slice(0, 512);
    const source = e.filename ? stripQuery(e.filename) : '';
    const props: Record<string, unknown> = {
      type: 'error', message, source, lineno: e.lineno ?? 0, colno: e.colno ?? 0,
    };
    const stack = e.error instanceof Error && e.error.stack ? scrub(e.error.stack).slice(0, 2000) : '';
    if (stack) props['stack'] = stack;
    emit(props, `${message}|${source}|${e.lineno ?? 0}`);
  }

  function handleRejection(e: PromiseRejectionEvent): void {
    const reason = e.reason;
    const message = scrub(reason instanceof Error ? (reason.message ?? '') : String(reason ?? '')).slice(0, 512);
    const props: Record<string, unknown> = { type: 'unhandledrejection', message, source: '', lineno: 0, colno: 0 };
    const stack = reason instanceof Error && reason.stack ? scrub(reason.stack).slice(0, 2000) : '';
    if (stack) props['stack'] = stack;
    emit(props, `${message}|rejection`);
  }

  function mount(p: Push): void {
    if (typeof window === 'undefined') return;
    push = p;
    onError = handleError;
    window.addEventListener('error', onError);
    onRejection = handleRejection;
    window.addEventListener('unhandledrejection', onRejection);
  }

  function destroy(): void {
    if (typeof window !== 'undefined') {
      if (onError) window.removeEventListener('error', onError);
      if (onRejection) window.removeEventListener('unhandledrejection', onRejection);
    }
    seen.clear();
    push = onError = onRejection = undefined;
  }

  return { mount, destroy };
}
