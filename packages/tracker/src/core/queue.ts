/**
 * Statflow Tracker — In-memory event queue and flush scheduler.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

import type { StatflowEvent, ResolvedConfig } from './config.js';
import { BROWSER } from './env.js';
import { sendBatch, markUnloading, resetUnloading } from './transport.js';

export class EventQueue {
  private readonly _q: StatflowEvent[] = [];
  private _timer: ReturnType<typeof setInterval> | undefined;
  private _inflight = false;
  private _pending = false;
  private readonly _cfg: ResolvedConfig;
  private _listeners: Array<[string, EventListener]> = [];

  constructor(config: ResolvedConfig) { this._cfg = config; }

  get length(): number { return this._q.length; }

  push(event: StatflowEvent): void {
    const e = this._cfg.beforeSend ? this._cfg.beforeSend(event) : event;
    if (e == null) return;
    this._q.push(e);
    if (this._q.length >= this._cfg.batchSize) void this.flush(false);
  }

  start(): void {
    this._timer = setInterval(() => {
      if (this._q.length > 0) void this.flush(false);
    }, this._cfg.batchIntervalMs);

    if (BROWSER) {
      const onHide = (): void => { markUnloading(); void this.flush(true); };
      const onVisibility: EventListener = () => {
        if (document.visibilityState === 'hidden') onHide();
        else resetUnloading();
      };
      const onShow: EventListener = () => { resetUnloading(); };
      window.addEventListener('visibilitychange', onVisibility);
      window.addEventListener('pagehide', onHide);
      window.addEventListener('pageshow', onShow);
      this._listeners = [['visibilitychange', onVisibility], ['pagehide', onHide], ['pageshow', onShow]];
    }
  }

  async flush(unload: boolean): Promise<void> {
    if (unload) markUnloading();
    if (this._inflight) { this._pending = true; return; }
    if (this._q.length === 0) return;
    const batch = this._q.splice(0);
    this._inflight = true;
    try {
      await sendBatch(batch, `${this._cfg.apiBase}${this._cfg.apiPath}`);
    } finally {
      this._inflight = false;
      if (this._pending) {
        this._pending = false;
        if (this._q.length > 0) await this.flush(false);
      }
    }
  }

  destroy(): void {
    if (this._timer != null) { clearInterval(this._timer); this._timer = undefined; }
    if (BROWSER) for (const [t, fn] of this._listeners) window.removeEventListener(t, fn);
    this._listeners = [];
  }
}
