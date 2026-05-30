/** Shared test factory for internal StatflowEvent fixtures. */
import type { BehavioralSignals, StatflowEvent } from '../src/core/config.js';

export function makeEvent(
  name = 'pageview',
  extra: Partial<StatflowEvent> = {},
): StatflowEvent {
  return {
    eid: '8f14e45f-ce64-4f1a-9b2d-2c3a4b5c6d7e',
    k: 'stk_test',
    event: name,
    ts: Date.now(),
    seq: 1,
    url: 'https://example.com/pricing',
    pathname: '/pricing',
    hostname: 'example.com',
    tv: '0.1.0',
    vw: 1280,
    vh: 720,
    sw: 1920,
    sh: 1080,
    ...extra,
  };
}

export function withBehavioral(b: BehavioralSignals): Partial<StatflowEvent> {
  return { behavioral: b };
}
