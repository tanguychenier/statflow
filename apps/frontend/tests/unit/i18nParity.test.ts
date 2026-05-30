// =============================================================================
// Statflow Dashboard — i18n catalogue parity tests (i18n.md §8.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'

function flatKeys(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return value && typeof value === 'object'
      ? flatKeys(value as Record<string, unknown>, path)
      : [path]
  })
}

describe('i18n catalogues', () => {
  const enKeys = flatKeys(en).sort()
  const frKeys = flatKeys(fr).sort()

  it('EN and FR have an identical key set', () => {
    expect(frKeys).toEqual(enKeys)
  })

  it('exposes the navigation keys the router/sidebar rely on', () => {
    for (const key of [
      'nav.overview',
      'nav.realtime',
      'nav.heatmaps',
      'nav.pagesAndSources',
      'nav.funnels',
      'nav.settings',
    ]) {
      expect(enKeys).toContain(key)
    }
  })

  it('provides the dimension + operator labels the filter builder needs', () => {
    expect(enKeys).toContain('dimensions.pathname')
    expect(enKeys).toContain('operators.contains')
    expect(enKeys).toContain('charts.viewAsTable')
  })
})
