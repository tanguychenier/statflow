/**
 * Statflow Tracker — Fixed-capacity ring buffer for heatmap points.
 *
 * High-frequency pointer sampling can outrun the flush cadence on a long-lived
 * page. A ring buffer bounds memory to `capacity` points: once full, each new
 * point overwrites the oldest, so the buffer always holds the most recent
 * activity rather than growing without limit (design §8.2, budget §7.1).
 *
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

export class RingBuffer<T> {
  private readonly _store: T[];
  private readonly _cap: number;
  private _head = 0;
  private _size = 0;

  constructor(capacity: number) {
    // A zero/negative capacity would make the buffer useless and the modulo
    // undefined; clamp to at least one slot.
    this._cap = capacity > 0 ? Math.floor(capacity) : 1;
    this._store = new Array<T>(this._cap);
  }

  get size(): number {
    return this._size;
  }

  get capacity(): number {
    return this._cap;
  }

  get isFull(): boolean {
    return this._size === this._cap;
  }

  push(item: T): void {
    this._store[this._head] = item;
    this._head = (this._head + 1) % this._cap;
    if (this._size < this._cap) this._size++;
  }

  /** Drain in chronological (oldest → newest) order and reset the buffer. */
  drain(): T[] {
    const out = this.toArray();
    this.clear();
    return out;
  }

  toArray(): T[] {
    const out: T[] = [];
    // When full, the oldest item sits at `_head`; before that, items start at 0.
    const start = this._size === this._cap ? this._head : 0;
    for (let i = 0; i < this._size; i++) {
      out.push(this._store[(start + i) % this._cap]!);
    }
    return out;
  }

  clear(): void {
    this._head = 0;
    this._size = 0;
  }
}
