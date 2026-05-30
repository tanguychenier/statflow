import { describe, it, expect, afterEach } from 'vitest';
import { ancestorHref, buildSelector, docPoint, elementBehavioral, scrubText } from '../../src/core/dom.js';

afterEach(() => {
  document.body.innerHTML = '';
});

describe('buildSelector', () => {
  it('prefers data-track-id', () => {
    const el = document.createElement('div');
    el.setAttribute('data-track-id', 'hero');
    document.body.appendChild(el);
    expect(buildSelector(el)).toBe('[data-track-id="hero"]');
  });

  it('uses a stable id', () => {
    const el = document.createElement('button');
    el.id = 'go';
    document.body.appendChild(el);
    expect(buildSelector(el)).toBe('button#go');
  });

  it('drops dynamic class tokens (hashes / numbers)', () => {
    const el = document.createElement('div');
    el.className = 'card a1b2c3d4e5f6a7b8 1234';
    document.body.appendChild(el);
    const sel = buildSelector(el);
    expect(sel).toContain('card');
    expect(sel).not.toContain('a1b2c3d4e5f6a7b8');
    expect(sel).not.toContain('1234');
  });

  it('respects a custom max depth', () => {
    const a = document.createElement('section');
    const b = document.createElement('div');
    const c = document.createElement('span');
    a.appendChild(b); b.appendChild(c);
    document.body.appendChild(a);
    expect(buildSelector(c, 1)).toBe('span');
  });
});

describe('ancestorHref', () => {
  it('returns href from an ancestor anchor', () => {
    const a = document.createElement('a');
    a.setAttribute('href', '/x');
    const span = document.createElement('span');
    a.appendChild(span);
    document.body.appendChild(a);
    expect(ancestorHref(span)).toBe('/x');
  });

  it('returns undefined when no anchor ancestor exists', () => {
    const el = document.createElement('div');
    document.body.appendChild(el);
    expect(ancestorHref(el)).toBeUndefined();
  });
});

describe('scrubText', () => {
  it('redacts emails and trims', () => {
    expect(scrubText('  mail me at a@b.co please  ')).toBe('mail me at [redacted] please');
  });

  it('caps length', () => {
    expect(scrubText('x'.repeat(500), 10).length).toBeLessThanOrEqual(10);
  });
});

describe('docPoint', () => {
  it('computes document-relative coordinates and percentages', () => {
    Object.defineProperty(document.body, 'scrollWidth', { value: 1000, configurable: true });
    Object.defineProperty(document.body, 'scrollHeight', { value: 2000, configurable: true });
    const p = docPoint(100, 400);
    expect(p.x).toBe(100 + window.scrollX);
    expect(p.y).toBe(400 + window.scrollY);
    expect(p.xp).toBeCloseTo(10, 1);
    expect(p.yp).toBeCloseTo(20, 1);
  });

  it('guards against a zero-sized document', () => {
    Object.defineProperty(document.body, 'scrollWidth', { value: 0, configurable: true });
    Object.defineProperty(document.body, 'scrollHeight', { value: 0, configurable: true });
    const p = docPoint(0, 0);
    expect(Number.isFinite(p.xp)).toBe(true);
    expect(Number.isFinite(p.yp)).toBe(true);
  });
});

describe('elementBehavioral', () => {
  it('assembles a behavioral block with element identity', () => {
    const btn = document.createElement('button');
    btn.id = 'cta';
    btn.textContent = 'Buy now';
    document.body.appendChild(btn);
    const b = elementBehavioral(btn, docPoint(10, 20));
    expect(b.element_tag).toBe('button');
    expect(b.element_id).toBe('cta');
    expect(b.element_text).toBe('Buy now');
    expect(b.element_selector).toContain('button');
    expect(typeof b.click_x).toBe('number');
  });

  it('omits text and id when absent', () => {
    const div = document.createElement('div');
    document.body.appendChild(div);
    const b = elementBehavioral(div, docPoint(0, 0));
    expect(b.element_text).toBeUndefined();
    expect(b.element_id).toBeUndefined();
  });
});
