import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createFormsCollector } from '../../src/collectors/forms.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('forms collector — branch coverage', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createFormsCollector>;

  beforeEach(() => {
    resetSeq();
    document.body.innerHTML = '';
    collected = [];
    collector = createFormsCollector(resolveConfig({ siteKey: 'stk_forms_b' }));
    collector.mount((e) => collected.push(e));
  });

  afterEach(() => {
    collector.destroy();
  });

  it('ignores a control that is not inside any form', () => {
    const loose = document.createElement('input');
    loose.name = 'orphan';
    document.body.appendChild(loose);
    loose.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(collected.find((e) => e.event === 'form_focus')).toBeUndefined();
  });

  it('emits form_submit with duration 0 when the form was never focused', () => {
    const form = document.createElement('form');
    form.name = 'search';
    const input = document.createElement('input');
    input.name = 'q';
    form.appendChild(input);
    document.body.appendChild(form);

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    const submit = collected.find((e) => e.event === 'form_submit')!;
    expect(submit.properties?.['duration_ms']).toBe(0);
    expect(submit.properties?.['form_action']).toBe(''); // no action attribute
  });

  it('counts a filled textarea and an unchecked radio correctly', () => {
    const form = document.createElement('form');
    form.id = 'mixed';
    const ta = document.createElement('textarea');
    ta.name = 'comment';
    ta.value = 'hello';
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'plan';
    radio.checked = false;
    const sel = document.createElement('select');
    sel.name = 'country';
    form.append(ta, radio, sel);
    document.body.appendChild(form);

    ta.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    const submit = collected.find((e) => e.event === 'form_submit')!;
    expect(submit.properties?.['field_count']).toBe(3);
    expect(submit.properties?.['filled_count']).toBe(1); // only the textarea
  });

  it('ignores a non-Element / non-form submit target', () => {
    expect(() => document.dispatchEvent(new Event('submit', { bubbles: true }))).not.toThrow();
    expect(collected.find((e) => e.event === 'form_submit')).toBeUndefined();
  });

  it('emits form_abandon only once even if flushed twice', () => {
    const form = document.createElement('form');
    form.id = 'once';
    const input = document.createElement('input');
    input.name = 'a';
    form.appendChild(input);
    document.body.appendChild(form);

    input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    collector.flushAbandons(true);
    collector.flushAbandons(true);

    expect(collected.filter((e) => e.event === 'form_abandon')).toHaveLength(1);
  });
});
