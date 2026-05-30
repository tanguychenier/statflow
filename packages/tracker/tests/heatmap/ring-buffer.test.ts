import { describe, it, expect } from 'vitest';
import { RingBuffer } from '../../src/heatmap/ring-buffer.js';

describe('RingBuffer', () => {
  it('reports an empty buffer initially', () => {
    const rb = new RingBuffer<number>(3);
    expect(rb.size).toBe(0);
    expect(rb.capacity).toBe(3);
    expect(rb.isFull).toBe(false);
    expect(rb.toArray()).toEqual([]);
  });

  it('preserves insertion order below capacity', () => {
    const rb = new RingBuffer<number>(5);
    rb.push(1);
    rb.push(2);
    rb.push(3);
    expect(rb.size).toBe(3);
    expect(rb.isFull).toBe(false);
    expect(rb.toArray()).toEqual([1, 2, 3]);
  });

  it('becomes full exactly at capacity', () => {
    const rb = new RingBuffer<number>(3);
    rb.push(1);
    rb.push(2);
    rb.push(3);
    expect(rb.isFull).toBe(true);
    expect(rb.size).toBe(3);
    expect(rb.toArray()).toEqual([1, 2, 3]);
  });

  it('overwrites the oldest item once full, keeping chronological order', () => {
    const rb = new RingBuffer<number>(3);
    rb.push(1);
    rb.push(2);
    rb.push(3);
    rb.push(4);
    expect(rb.size).toBe(3);
    expect(rb.toArray()).toEqual([2, 3, 4]);
    rb.push(5);
    expect(rb.toArray()).toEqual([3, 4, 5]);
  });

  it('wraps correctly across multiple full cycles', () => {
    const rb = new RingBuffer<number>(2);
    for (let i = 1; i <= 7; i++) rb.push(i);
    expect(rb.toArray()).toEqual([6, 7]);
  });

  it('drain returns chronological order and empties the buffer', () => {
    const rb = new RingBuffer<number>(3);
    rb.push(1);
    rb.push(2);
    rb.push(3);
    rb.push(4);
    expect(rb.drain()).toEqual([2, 3, 4]);
    expect(rb.size).toBe(0);
    expect(rb.toArray()).toEqual([]);
  });

  it('clear resets size and head', () => {
    const rb = new RingBuffer<number>(3);
    rb.push(1);
    rb.push(2);
    rb.clear();
    expect(rb.size).toBe(0);
    rb.push(9);
    expect(rb.toArray()).toEqual([9]);
  });

  it('clamps a zero capacity to a single usable slot', () => {
    const rb = new RingBuffer<number>(0);
    expect(rb.capacity).toBe(1);
    rb.push(1);
    rb.push(2);
    expect(rb.toArray()).toEqual([2]);
  });

  it('clamps a negative capacity to a single usable slot', () => {
    const rb = new RingBuffer<number>(-5);
    expect(rb.capacity).toBe(1);
  });

  it('floors a fractional capacity', () => {
    const rb = new RingBuffer<number>(2.9);
    expect(rb.capacity).toBe(2);
  });
});
