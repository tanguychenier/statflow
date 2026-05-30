import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createClickCollector, buildSelector } from '../../src/collectors/clicks.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

const config = resolveConfig({ siteKey: 'stk_click_test' });

function fireClick(target: Element, opts: Partial<MouseEventInit> = {}): void {
  target.dispatchEvent(
    new MouseEvent('click', {
      bubbles: true,
      cancelable: true,
      clientX: opts.clientX ?? 100,
      clientY: opts.clientY ?? 200,
      button: opts.button ?? 0,
      shiftKey: opts.shiftKey ?? false,
      metaKey: opts.metaKey ?? false,
      ctrlKey: opts.ctrlKey ?? false,
      ...opts,
    }),
  );
}

describe('buildSelector', () => {
  it('uses data-track-id when present', () => {
    const el = document.createElement('div');
    el.setAttribute('data-track-id', 'hero-cta');
    document.body.appendChild(el);
    expect(buildSelector(el)).toBe('[data-track-id="hero-cta"]');
    document.body.removeChild(el);
  });

  it('uses id when present and stable', () => {
    const el = document.createElement('button');
    el.id = 'submit-btn';
    document.body.appendChild(el);
    expect(buildSelector(el)).toContain('button#submit-btn');
    document.body.removeChild(el);
  });

  it('uses tag + class for elements without id', () => {
    const el = document.createElement('a');
    el.classList.add('nav-link');
    document.body.appendChild(el);
    const sel = buildSelector(el);
    expect(sel).toContain('a');
    expect(sel).toContain('nav-link');
    document.body.removeChild(el);
  });

  it('skips dynamic ids that look like UUIDs', () => {
    const el = document.createElement('div');
    el.id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    document.body.appendChild(el);
    expect(buildSelector(el)).not.toContain('a1b2c3d4');
    document.body.removeChild(el);
  });

  it('caps selector length at 256 chars', () => {
    let root = document.body;
    const elements: Element[] = [];
    for (let i = 0; i < 10; i++) {
      const el = document.createElement('section');
      el.classList.add(`level-${i}`);
      root.appendChild(el);
      elements.push(el);
      root = el;
    }
    const leaf = elements[elements.length - 1]!;
    expect(buildSelector(leaf).length).toBeLessThanOrEqual(256);
    document.body.removeChild(elements[0]!);
  });
});

describe('createClickCollector', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createClickCollector>;

  beforeEach(() => {
    resetSeq();
    collected = [];
    collector = createClickCollector(config);
    collector.mount((event) => collected.push(event));
  });

  afterEach(() => {
    collector.destroy();
    collected = [];
  });

  it('captures a basic click with element data in the behavioral block', () => {
    const btn = document.createElement('button');
    btn.textContent = 'Click me';
    document.body.appendChild(btn);

    fireClick(btn, { clientX: 50, clientY: 75 });

    const ev = collected.find((e) => e.event === 'click');
    expect(ev).toBeDefined();
    expect(ev!.behavioral?.element_tag).toBe('button');
    expect(ev!.behavioral?.element_text).toBe('Click me');
    expect(typeof ev!.behavioral?.click_x).toBe('number');
    expect(typeof ev!.behavioral?.click_y).toBe('number');
    expect(typeof ev!.behavioral?.click_x_pct).toBe('number');
    expect(typeof ev!.behavioral?.click_y_pct).toBe('number');
    expect(ev!.behavioral?.element_selector).toContain('button');

    document.body.removeChild(btn);
  });

  it('records modifier keys in props', () => {
    const btn = document.createElement('button');
    document.body.appendChild(btn);

    fireClick(btn, { shiftKey: true, metaKey: true, ctrlKey: false });

    const ev = collected.find((e) => e.event === 'click');
    expect(ev!.properties?.['shift']).toBe(true);
    expect(ev!.properties?.['meta']).toBe(true);
    expect(ev!.properties?.['ctrl']).toBe(false);

    document.body.removeChild(btn);
  });

  it('captures href from ancestor <a> element in props (resolved + sanitised)', () => {
    const link = document.createElement('a');
    link.setAttribute('href', '/pricing');
    const span = document.createElement('span');
    span.textContent = 'Pricing';
    link.appendChild(span);
    document.body.appendChild(link);

    fireClick(span);

    const ev = collected.find((e) => e.event === 'click');
    // The relative href is routed through sanitiseUrl, which resolves it against
    // the current origin; the path is preserved.
    expect(String(ev!.properties?.['href'])).toMatch(/\/pricing$/);

    document.body.removeChild(link);
  });

  it('routes the link href through sanitiseUrl: PII in the query string is redacted, never leaked raw', () => {
    const link = document.createElement('a');
    link.setAttribute('href', 'https://shop.example.com/buy?token=topsecret&email=a@b.com&sku=42');
    document.body.appendChild(link);

    fireClick(link);

    const ev = collected.find((e) => e.event === 'click');
    const href = String(ev!.properties?.['href']);
    expect(href).not.toContain('topsecret');
    expect(href).not.toContain('a@b.com');
    expect(href).toMatch(/token=%5Bredacted%5D|token=\[redacted\]/i);
    expect(href).toMatch(/email=%5Bredacted%5D|email=\[redacted\]/i);
    expect(href).toContain('sku=42');

    document.body.removeChild(link);
  });

  it('records element id in the behavioral block', () => {
    const input = document.createElement('input');
    input.id = 'email-field';
    input.setAttribute('name', 'newsletter');
    document.body.appendChild(input);

    fireClick(input);

    const ev = collected.find((e) => e.event === 'click');
    expect(ev!.behavioral?.element_id).toBe('email-field');
    expect(ev!.properties?.['attr_name']).toBe('newsletter');

    document.body.removeChild(input);
  });

  it('detects rage_click after 3 clicks on the same target within the window', () => {
    const btn = document.createElement('button');
    btn.id = 'rage-target';
    document.body.appendChild(btn);

    fireClick(btn);
    fireClick(btn);
    fireClick(btn);

    const rage = collected.find((e) => e.event === 'rage_click');
    expect(rage).toBeDefined();
    expect(rage!.properties?.['count']).toBeGreaterThanOrEqual(3);
    expect(rage!.behavioral?.is_rage_click).toBe(true);

    document.body.removeChild(btn);
  });

  it('does not emit rage_click for only 2 clicks', () => {
    const btn = document.createElement('button');
    btn.id = 'no-rage';
    document.body.appendChild(btn);

    fireClick(btn);
    fireClick(btn);

    expect(collected.find((e) => e.event === 'rage_click')).toBeUndefined();

    document.body.removeChild(btn);
  });

  it('does not capture click when target is not an Element', () => {
    expect(() => fireClick(document.body)).not.toThrow();
  });

  it('destroy() stops capturing clicks', () => {
    const btn = document.createElement('button');
    document.body.appendChild(btn);

    collector.destroy();
    fireClick(btn);

    expect(collected.find((e) => e.event === 'click')).toBeUndefined();
    document.body.removeChild(btn);
  });

  it('scrubs email addresses from element text', () => {
    const btn = document.createElement('button');
    btn.textContent = 'Contact user@example.com';
    document.body.appendChild(btn);

    fireClick(btn);

    const ev = collected.find((e) => e.event === 'click');
    expect(ev!.behavioral?.element_text).not.toContain('user@example.com');
    expect(ev!.behavioral?.element_text).toContain('[redacted]');

    document.body.removeChild(btn);
  });
});

describe('dead-click detection', () => {
  let collected: StatflowEvent[];
  let collector: ReturnType<typeof createClickCollector>;

  beforeEach(() => {
    vi.useFakeTimers();
    resetSeq();
    collected = [];
    collector = createClickCollector(resolveConfig({ siteKey: 'stk_dead', deadClickFenceMs: 300 }));
    collector.mount((event) => collected.push(event));
  });

  afterEach(() => {
    collector.destroy();
    vi.useRealTimers();
  });

  it('emits dead_click when nothing changes within the fence window', async () => {
    const div = document.createElement('div');
    div.className = 'card-overlay';
    document.body.appendChild(div);

    fireClick(div);
    await vi.advanceTimersByTimeAsync(350);

    const dead = collected.find((e) => e.event === 'dead_click');
    expect(dead).toBeDefined();
    expect(dead!.properties?.['fence_ms']).toBe(300);
    expect(dead!.behavioral?.element_selector).toContain('div');

    document.body.removeChild(div);
  });

  it('does NOT emit dead_click when the DOM mutates after the click', async () => {
    const div = document.createElement('div');
    document.body.appendChild(div);

    fireClick(div);
    // mutate the DOM within the fence
    const extra = document.createElement('span');
    document.body.appendChild(extra);
    await vi.advanceTimersByTimeAsync(350);

    expect(collected.find((e) => e.event === 'dead_click')).toBeUndefined();

    document.body.removeChild(div);
    document.body.removeChild(extra);
  });
});
