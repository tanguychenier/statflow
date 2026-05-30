import { describe, it, expect } from 'vitest';
import { resolveConfig } from '../../src/core/config.js';

describe('resolveConfig', () => {
  it('applies all defaults when only siteKey is given', () => {
    const cfg = resolveConfig({ siteKey: 'stk_test' });

    expect(cfg.siteKey).toBe('stk_test');
    expect(cfg.apiPath).toBe('/api/v1/events');
    expect(cfg.batchSize).toBe(20);
    expect(cfg.batchIntervalMs).toBe(5_000);
    expect(cfg.scrollThresholds).toEqual([25, 50, 75, 90, 100]);
    expect(cfg.engagementIntervalMs).toBe(10_000);
    expect(cfg.rageWindowMs).toBe(1_000);
    expect(cfg.deadClickFenceMs).toBe(300);
    expect(cfg.visibilityThreshold).toBe(0.5);
    expect(cfg.disable).toEqual([]);
    expect(cfg.sampleRate).toBe(1);
    expect(cfg.dnt).toBe('disable');
    expect(cfg.beforeSend).toBeUndefined();
  });

  it('accepts the deprecated projectId alias as the site key', () => {
    const cfg = resolveConfig({ projectId: 'proj_legacy' });
    expect(cfg.siteKey).toBe('proj_legacy');
  });

  it('prefers siteKey over projectId when both are present', () => {
    const cfg = resolveConfig({ siteKey: 'stk_new', projectId: 'proj_legacy' });
    expect(cfg.siteKey).toBe('stk_new');
  });

  it('preserves all explicit overrides', () => {
    const beforeSend = (e: unknown): never => e as never;
    const cfg = resolveConfig({
      siteKey: 'stk_abc',
      apiBase: 'https://custom.example.com',
      apiPath: '/api/sf/events',
      batchSize: 10,
      batchIntervalMs: 2_000,
      scrollThresholds: [50, 100],
      engagementIntervalMs: 5_000,
      rageWindowMs: 800,
      deadClickFenceMs: 250,
      visibilityThreshold: 0.75,
      disable: ['forms', 'vitals'],
      beforeSend,
      sampleRate: 0.5,
      dnt: 'ignore',
    });

    expect(cfg.apiBase).toBe('https://custom.example.com');
    expect(cfg.apiPath).toBe('/api/sf/events');
    expect(cfg.batchSize).toBe(10);
    expect(cfg.batchIntervalMs).toBe(2_000);
    expect(cfg.scrollThresholds).toEqual([50, 100]);
    expect(cfg.engagementIntervalMs).toBe(5_000);
    expect(cfg.rageWindowMs).toBe(800);
    expect(cfg.deadClickFenceMs).toBe(250);
    expect(cfg.visibilityThreshold).toBe(0.75);
    expect(cfg.disable).toEqual(['forms', 'vitals']);
    expect(cfg.beforeSend).toBe(beforeSend);
    expect(cfg.sampleRate).toBe(0.5);
    expect(cfg.dnt).toBe('ignore');
  });

  it('falls back to window.location.origin for apiBase when window is available', () => {
    const cfg = resolveConfig({ siteKey: 'stk_test' });
    expect(cfg.apiBase).toMatch(/^http:\/\/localhost/);
  });

  it('resolves an empty site key when neither siteKey nor projectId is supplied', () => {
    const cfg = resolveConfig({});
    expect(cfg.siteKey).toBe('');
  });
});
