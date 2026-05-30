import { describe, it, expect } from 'vitest';
import { mount } from '../../src/behavior/index.js';
import { resolveConfig } from '../../src/core/config.js';
import { resetSeq } from '../../src/core/ids.js';
import type { StatflowEvent } from '../../src/core/config.js';

describe('behavior entry — mount()', () => {
  it('mounts the enabled collectors and captures a click through the core push', () => {
    resetSeq();
    const events: StatflowEvent[] = [];
    const teardown = mount(
      resolveConfig({ siteKey: 'stk_beh', disable: ['scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'] }),
      (e) => events.push(e),
    );

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

  it('returns a teardown that does not throw when collectors are all disabled', () => {
    const teardown = mount(
      resolveConfig({ siteKey: 'stk_beh2', disable: ['clicks', 'scroll', 'forms', 'engagement', 'visibility', 'vitals', 'errors'] }),
      () => undefined,
    );
    expect(() => teardown()).not.toThrow();
  });
});
