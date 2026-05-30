/**
 * Statflow Tracker — Form-interaction collector.
 *
 * Emits the structural lifecycle of a form: first focus, submit, and abandon
 * (focused but left without submitting). Field VALUES are never read — only
 * structural metadata (field name/type, field counts, fill counts). "Filled" is
 * derived purely from whether a control currently holds any value, never from
 * what that value is.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { ResolvedConfig, StatflowEvent } from '../core/config.js';
import { buildEnvelope, sanitiseUrl } from '../core/envelope.js';
import { buildSelector } from '../core/dom.js';

type Push = (e: StatflowEvent) => void;

interface FormState {
  form: HTMLFormElement;
  focusedAt: number;
  lastField: string;
  lastFieldType: string;
  submitted: boolean;
}

export interface FormsCollector { mount(p: Push): void; flushAbandons(onUnload: boolean): void; destroy(): void; }

const CONTROL = 'input,select,textarea';
const IGNORED_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image', 'file', 'password']);

function formKey(form: HTMLFormElement): string {
  return form.getAttribute('id') || form.getAttribute('name') || buildSelector(form);
}

function fieldName(el: Element): string {
  return el.getAttribute('name') || el.getAttribute('id') || el.tagName.toLowerCase();
}

function fieldType(el: Element): string {
  return el.getAttribute('type') || el.tagName.toLowerCase();
}

function counts(form: HTMLFormElement): { field_count: number; filled_count: number } {
  const fields = [...form.querySelectorAll(CONTROL)].filter(f => !IGNORED_TYPES.has((f.getAttribute('type') ?? '').toLowerCase()));
  let filled = 0;
  for (const f of fields) {
    const el = f as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
    if (el.type === 'checkbox' || el.type === 'radio') { if ((el as HTMLInputElement).checked) filled++; }
    else if ((el.value ?? '').length > 0) filled++;
  }
  return { field_count: fields.length, filled_count: filled };
}

export function createFormsCollector(cfg: ResolvedConfig): FormsCollector {
  const states = new Map<HTMLFormElement, FormState>();
  let push: Push | undefined;
  const winListeners: Array<[string, EventListener]> = [];
  const docListeners: Array<[string, EventListener, boolean]> = [];

  function envProps(form: HTMLFormElement, extra: Record<string, unknown>): Record<string, unknown> {
    const action = form.getAttribute('action');
    return { form_id: formKey(form), form_action: action ? sanitiseUrl(action) : '', ...extra };
  }

  function handleFocus(e: Event): void {
    const t = e.target;
    if (!push || !(t instanceof Element) || !t.matches(CONTROL)) return;
    const form = (t as HTMLInputElement).form ?? t.closest('form');
    if (!form) return;
    const name = fieldName(t), type = fieldType(t);
    let st = states.get(form);
    if (!st) {
      st = { form, focusedAt: Date.now(), lastField: name, lastFieldType: type, submitted: false };
      states.set(form, st);
      push(buildEnvelope(cfg, { name: 'form_focus', properties: envProps(form, { field_name: name, field_type: type }) }));
    } else {
      st.lastField = name;
      st.lastFieldType = type;
    }
  }

  function handleSubmit(e: Event): void {
    const form = e.target;
    if (!push || !(form instanceof HTMLFormElement)) return;
    const st = states.get(form);
    const { field_count, filled_count } = counts(form);
    push(buildEnvelope(cfg, {
      name: 'form_submit',
      properties: envProps(form, { field_count, filled_count, duration_ms: st ? (Date.now() - st.focusedAt) | 0 : 0 }),
    }));
    if (st) st.submitted = true;
  }

  function flushAbandons(_onUnload: boolean): void {
    if (!push) return;
    for (const st of states.values()) {
      if (st.submitted) continue;
      st.submitted = true;
      const { field_count, filled_count } = counts(st.form);
      push(buildEnvelope(cfg, {
        name: 'form_abandon',
        properties: envProps(st.form, {
          last_field: st.lastField, last_field_type: st.lastFieldType,
          field_count, filled_count, duration_ms: (Date.now() - st.focusedAt) | 0,
        }),
      }));
    }
    states.clear();
  }

  function mount(p: Push): void {
    if (typeof document === 'undefined') return;
    push = p;
    const onFocus: EventListener = handleFocus;
    document.addEventListener('focusin', onFocus, { passive: true, capture: true });
    docListeners.push(['focusin', onFocus, true]);
    const onSubmit: EventListener = handleSubmit;
    document.addEventListener('submit', onSubmit, { capture: true });
    docListeners.push(['submit', onSubmit, true]);
    const onVis: EventListener = () => { if (document.visibilityState === 'hidden') flushAbandons(true); };
    document.addEventListener('visibilitychange', onVis);
    docListeners.push(['visibilitychange', onVis, false]);
    if (typeof window !== 'undefined') {
      const onNav: EventListener = () => flushAbandons(false);
      window.addEventListener('sf:nav', onNav);
      winListeners.push(['sf:nav', onNav]);
      const onHide: EventListener = () => flushAbandons(true);
      window.addEventListener('pagehide', onHide);
      winListeners.push(['pagehide', onHide]);
    }
  }

  function destroy(): void {
    if (typeof document !== 'undefined') for (const [t, fn, cap] of docListeners) document.removeEventListener(t, fn, { capture: cap });
    if (typeof window !== 'undefined') for (const [t, fn] of winListeners) window.removeEventListener(t, fn);
    docListeners.length = 0;
    winListeners.length = 0;
    states.clear();
    push = undefined;
  }

  return { mount, flushAbandons, destroy };
}
