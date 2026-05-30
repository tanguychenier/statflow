import { describe, it, expect } from 'vitest';
import { resolveHeatmapConfig } from '../../src/heatmap/config.js';

describe('resolveHeatmapConfig', () => {
  it('applies the documented defaults', () => {
    const cfg = resolveHeatmapConfig({ siteKey: 'stk_abc' });
    expect(cfg.siteKey).toBe('stk_abc');
    expect(cfg.apiPath).toBe('/api/v1/events');
    expect(cfg.sampleIntervalMs).toBe(50);
    expect(cfg.sampleRate).toBe(1);
    expect(cfg.maxPoints).toBe(2000);
    expect(cfg.flushIntervalMs).toBe(5000);
    expect(cfg.dnt).toBe('disable');
  });

  it('defaults apiBase to the page origin', () => {
    const cfg = resolveHeatmapConfig({ siteKey: 'stk_abc' });
    expect(cfg.apiBase).toBe(window.location.origin);
  });

  it('accepts projectId as a back-compat alias for siteKey', () => {
    const cfg = resolveHeatmapConfig({ projectId: 'legacy_key' });
    expect(cfg.siteKey).toBe('legacy_key');
  });

  it('prefers siteKey over projectId when both are given', () => {
    const cfg = resolveHeatmapConfig({ siteKey: 'new', projectId: 'old' });
    expect(cfg.siteKey).toBe('new');
  });

  it('falls back to an empty site key when neither is provided', () => {
    expect(resolveHeatmapConfig({}).siteKey).toBe('');
  });

  it('honours explicit overrides', () => {
    const cfg = resolveHeatmapConfig({
      siteKey: 'stk_abc',
      apiBase: 'https://proxy.example',
      apiPath: '/collect',
      sampleIntervalMs: 100,
      maxPoints: 500,
      flushIntervalMs: 2000,
      dnt: 'ignore',
    });
    expect(cfg.apiBase).toBe('https://proxy.example');
    expect(cfg.apiPath).toBe('/collect');
    expect(cfg.sampleIntervalMs).toBe(100);
    expect(cfg.maxPoints).toBe(500);
    expect(cfg.flushIntervalMs).toBe(2000);
    expect(cfg.dnt).toBe('ignore');
  });

  it('clamps a sample rate above 1 down to 1', () => {
    expect(resolveHeatmapConfig({ sampleRate: 5 }).sampleRate).toBe(1);
  });

  it('clamps a negative sample rate up to 0', () => {
    expect(resolveHeatmapConfig({ sampleRate: -2 }).sampleRate).toBe(0);
  });

  it('passes through a valid fractional sample rate', () => {
    expect(resolveHeatmapConfig({ sampleRate: 0.25 }).sampleRate).toBe(0.25);
  });
});
