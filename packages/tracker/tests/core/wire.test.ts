import { describe, it, expect } from 'vitest';
import { toWire } from '../../src/core/wire.js';
import { makeEvent } from '../helpers.js';

describe('toWire', () => {
  it('maps the common envelope to short keys', () => {
    const w = toWire(makeEvent('pageview'));
    expect(w).toMatchObject({
      eid: '8f14e45f-ce64-4f1a-9b2d-2c3a4b5c6d7e',
      k: 'stk_test',
      e: 'pageview',
      u: 'https://example.com/pricing',
      p: '/pricing',
      h: 'example.com',
      vw: 1280, vh: 720, sw: 1920, sh: 1080,
      tv: '0.1.0',
    });
    expect(typeof w.ts).toBe('number');
    expect(typeof w.seq).toBe('number');
  });

  it('omits optional fields when absent', () => {
    const w = toWire(makeEvent());
    expect(w.r).toBeUndefined();
    expect(w.t).toBeUndefined();
    expect(w.dpr).toBeUndefined();
    expect(w.ct).toBeUndefined();
    expect(w.props).toBeUndefined();
    expect(w.b).toBeUndefined();
  });

  it('includes optional envelope fields when present', () => {
    const w = toWire(makeEvent('pageview', { ref: 'https://google.com/', title: 'Home', dpr: 2, ct: '4g' }));
    expect(w.r).toBe('https://google.com/');
    expect(w.t).toBe('Home');
    expect(w.dpr).toBe(2);
    expect(w.ct).toBe('4g');
  });

  it('passes through a flat custom_properties map as props (custom name rides event_name)', () => {
    const w = toWire(makeEvent('video_play', { properties: { id: 1, ok: true } }));
    expect(w.e).toBe('video_play');
    expect(w.props).toEqual({ id: 1, ok: true });
  });

  it('maps every behavioral signal to its short key', () => {
    const w = toWire(makeEvent('scroll_depth', {
      behavioral: {
        click_x: 1, click_y: 2, click_x_pct: 3.5, click_y_pct: 4.5,
        element_tag: 'button', element_text: 'Buy', element_selector: 'button.cta', element_id: 'buy',
        scroll_depth_pct: 75, scroll_depth_px: 1200, engagement_time_ms: 9000, is_rage_click: true,
      },
    }));
    expect(w.b).toEqual({
      cx: 1, cy: 2, cxp: 3.5, cyp: 4.5,
      etag: 'button', etxt: 'Buy', esel: 'button.cta', eid: 'buy',
      sd: 75, sdpx: 1200, em: 9000, rc: true,
    });
  });

  it('keeps a falsy-but-present behavioral value (is_rage_click=false)', () => {
    const w = toWire(makeEvent('click', { behavioral: { is_rage_click: false, click_x: 0 } }));
    expect(w.b).toEqual({ rc: false, cx: 0 });
  });

  it('does not leak any long internal key onto the wire', () => {
    const w = toWire(makeEvent('click', { properties: { a: 1 }, behavioral: { click_x: 5 } })) as Record<string, unknown>;
    for (const forbidden of ['event', 'url', 'pathname', 'hostname', 'properties', 'behavioral', 'pid', 'ua', 'ref', 'title']) {
      expect(w[forbidden]).toBeUndefined();
    }
  });
});
