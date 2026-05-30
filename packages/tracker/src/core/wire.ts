/**
 * Statflow Tracker — Internal event → compact wire format.
 * The wire form is the short-key representation defined in
 * docs/data-model/event-contract.md §2 (envelope) and §5 (behavioral `b`).
 * It is the ONLY representation that crosses the network. The ingestion layer
 * performs the single wire → canonical transformation on receipt.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { BehavioralSignals, StatflowEvent } from './config.js';

export interface WireBehavioral {
  cx?: number; cy?: number; cxp?: number; cyp?: number;
  etag?: string; etxt?: string; esel?: string; eid?: string;
  sd?: number; sdpx?: number; em?: number; rc?: boolean;
}

export interface WireEvent {
  eid: string; k: string; e: string; ts: number; seq: number;
  u: string; p: string; h: string;
  r?: string; t?: string;
  vw: number; vh: number; sw: number; sh: number;
  dpr?: number; ct?: string; tv: string;
  props?: Record<string, unknown>;
  b?: WireBehavioral;
}

function toWireBehavioral(b: BehavioralSignals): WireBehavioral {
  const w: WireBehavioral = {};
  if (b.click_x !== undefined) w.cx = b.click_x;
  if (b.click_y !== undefined) w.cy = b.click_y;
  if (b.click_x_pct !== undefined) w.cxp = b.click_x_pct;
  if (b.click_y_pct !== undefined) w.cyp = b.click_y_pct;
  if (b.element_tag !== undefined) w.etag = b.element_tag;
  if (b.element_text !== undefined) w.etxt = b.element_text;
  if (b.element_selector !== undefined) w.esel = b.element_selector;
  if (b.element_id !== undefined) w.eid = b.element_id;
  if (b.scroll_depth_pct !== undefined) w.sd = b.scroll_depth_pct;
  if (b.scroll_depth_px !== undefined) w.sdpx = b.scroll_depth_px;
  if (b.engagement_time_ms !== undefined) w.em = b.engagement_time_ms;
  if (b.is_rage_click !== undefined) w.rc = b.is_rage_click;
  return w;
}

export function toWire(e: StatflowEvent): WireEvent {
  const w: WireEvent = {
    eid: e.eid, k: e.k, e: e.event, ts: e.ts, seq: e.seq,
    u: e.url, p: e.pathname, h: e.hostname,
    vw: e.vw, vh: e.vh, sw: e.sw, sh: e.sh, tv: e.tv,
  };
  if (e.ref) w.r = e.ref;
  if (e.title) w.t = e.title;
  if (e.dpr !== undefined) w.dpr = e.dpr;
  if (e.ct) w.ct = e.ct;
  if (e.properties && Object.keys(e.properties).length) w.props = e.properties;
  if (e.behavioral && Object.keys(e.behavioral).length) w.b = toWireBehavioral(e.behavioral);
  return w;
}
