import { describe, it, expect, vi } from 'vitest';
import { mountAutocapture } from '../../src/collectors/autocapture.js';
import { resolveConfig } from '../../src/core/config.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('mountAutocapture', () => {
  it('mounts every enabled collector and tears them all down', () => {
    const events: StatflowEvent[] = [];
    const teardown = mountAutocapture(resolveConfig({ siteKey: 'stk_ac' }), (e) => events.push(e));

    const btn = document.createElement('button');
    document.body.appendChild(btn);
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(events.some((e) => e.event === 'click')).toBe(true);

    events.length = 0;
    teardown();
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(events.some((e) => e.event === 'click')).toBe(false);
    document.body.removeChild(btn);
  });

  it('skips disabled collectors', () => {
    const events: StatflowEvent[] = [];
    const teardown = mountAutocapture(
      resolveConfig({ siteKey: 'stk_ac2', disable: ['clicks', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'] }),
      (e) => events.push(e),
    );
    const btn = document.createElement('button');
    document.body.appendChild(btn);
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(events).toHaveLength(0);
    teardown();
    document.body.removeChild(btn);
  });

  it('isolates a collector whose mount throws and still returns a usable teardown', () => {
    // IntersectionObserver throwing would break the visibility collector mount;
    // the others must still register and the teardown must not throw.
    const Original = globalThis.IntersectionObserver;
    vi.stubGlobal('IntersectionObserver', class {
      constructor() { throw new Error('boom'); }
    });
    const events: StatflowEvent[] = [];
    const teardown = mountAutocapture(resolveConfig({ siteKey: 'stk_ac3' }), (e) => events.push(e));

    const btn = document.createElement('button');
    document.body.appendChild(btn);
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(events.some((e) => e.event === 'click')).toBe(true);

    expect(() => teardown()).not.toThrow();
    document.body.removeChild(btn);
    vi.stubGlobal('IntersectionObserver', Original);
  });
});
