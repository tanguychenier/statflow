import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createFormsCollector } from '../../src/collectors/forms.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

function buildForm(): HTMLFormElement {
  const form = document.createElement('form');
  form.id = 'checkout';
  form.setAttribute('action', '/api/checkout');
  const email = document.createElement('input');
  email.setAttribute('name', 'email');
  email.setAttribute('type', 'email');
  const card = document.createElement('input');
  card.setAttribute('name', 'card');
  card.setAttribute('type', 'tel');
  const hidden = document.createElement('input');
  hidden.setAttribute('type', 'hidden');
  hidden.setAttribute('name', 'csrf');
  form.append(email, card, hidden);
  document.body.appendChild(form);
  return form;
}

describe('createFormsCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createFormsCollector>;

  beforeEach(() => {
    resetSeq();
    document.body.innerHTML = '';
    collected = [];
    collector = createFormsCollector(resolveConfig({ siteKey: 'stk_forms' }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
  });

  it('emits form_focus exactly once on first field focus', () => {
    const form = buildForm();
    const email = form.querySelector('[name="email"]') as HTMLInputElement;
    const card = form.querySelector('[name="card"]') as HTMLInputElement;

    email.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    card.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    const focuses = collected.filter((e) => e.event === 'form_focus');
    expect(focuses).toHaveLength(1);
    expect(focuses[0]!.properties?.['form_id']).toBe('checkout');
    // The relative action is routed through sanitiseUrl (resolved against origin); path preserved.
    expect(String(focuses[0]!.properties?.['form_action'])).toMatch(/\/api\/checkout$/);
    expect(focuses[0]!.properties?.['field_name']).toBe('email');
    expect(focuses[0]!.properties?.['field_type']).toBe('email');
  });

  it('routes form_action through sanitiseUrl: PII in the action query string is redacted', () => {
    const form = document.createElement('form');
    form.id = 'pii-form';
    form.setAttribute('action', 'https://pay.example.com/submit?token=topsecret&email=a@b.com&ref=hp');
    const field = document.createElement('input');
    field.setAttribute('name', 'q');
    field.setAttribute('type', 'text');
    form.appendChild(field);
    document.body.appendChild(form);

    field.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    const focus = collected.find((e) => e.event === 'form_focus');
    const action = String(focus!.properties?.['form_action']);
    expect(action).not.toContain('topsecret');
    expect(action).not.toContain('a@b.com');
    expect(action).toMatch(/token=%5Bredacted%5D|token=\[redacted\]/i);
    expect(action).toContain('ref=hp');
  });

  it('emits form_submit with structural counts but never field values', () => {
    const form = buildForm();
    const email = form.querySelector('[name="email"]') as HTMLInputElement;
    email.value = 'secret@example.com';
    email.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    const submit = collected.find((e) => e.event === 'form_submit')!;
    expect(submit).toBeDefined();
    expect(submit.properties?.['field_count']).toBe(2); // hidden control excluded
    expect(submit.properties?.['filled_count']).toBe(1);
    expect(typeof submit.properties?.['duration_ms']).toBe('number');
    // no field value is present anywhere in the payload
    expect(JSON.stringify(submit)).not.toContain('secret@example.com');
  });

  it('emits form_abandon on the sf:nav lifecycle event when not submitted', () => {
    const form = buildForm();
    const card = form.querySelector('[name="card"]') as HTMLInputElement;
    card.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    window.dispatchEvent(new CustomEvent('sf:nav'));

    const abandon = collected.find((e) => e.event === 'form_abandon')!;
    expect(abandon).toBeDefined();
    expect(abandon.properties?.['last_field']).toBe('card');
    expect(abandon.properties?.['last_field_type']).toBe('tel');
    expect(abandon.properties?.['field_count']).toBe(2);
  });

  it('does NOT emit form_abandon when the form was submitted', () => {
    const form = buildForm();
    const email = form.querySelector('[name="email"]') as HTMLInputElement;
    email.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    collector.flushAbandons(true);

    expect(collected.find((e) => e.event === 'form_abandon')).toBeUndefined();
  });

  it('counts checked checkboxes as filled', () => {
    const form = document.createElement('form');
    form.id = 'prefs';
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.name = 'newsletter';
    cb.checked = true;
    form.appendChild(cb);
    document.body.appendChild(form);

    cb.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    const submit = collected.find((e) => e.event === 'form_submit')!;
    expect(submit.properties?.['filled_count']).toBe(1);
  });

  it('falls back to a synthesised key for an unnamed form', () => {
    const form = document.createElement('form');
    const input = document.createElement('input');
    input.name = 'q';
    form.appendChild(input);
    document.body.appendChild(form);

    input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    const focus = collected.find((e) => e.event === 'form_focus')!;
    expect(typeof focus.properties?.['form_id']).toBe('string');
    expect((focus.properties?.['form_id'] as string).length).toBeGreaterThan(0);
  });

  it('ignores focus events on non-form controls', () => {
    const div = document.createElement('div');
    div.tabIndex = 0;
    document.body.appendChild(div);
    div.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(collected.find((e) => e.event === 'form_focus')).toBeUndefined();
  });

  it('destroy() detaches listeners', () => {
    collector.destroy();
    const form = buildForm();
    const email = form.querySelector('[name="email"]') as HTMLInputElement;
    email.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(collected.find((e) => e.event === 'form_focus')).toBeUndefined();
  });
});
