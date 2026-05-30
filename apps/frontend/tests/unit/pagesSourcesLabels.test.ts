// =============================================================================
// Pages & Sources — display label tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for labels.ts
// =============================================================================

import { describe, it, expect } from 'vitest'
import { displayLabel, utmDisplayLabel } from '@/components/pagessources/labels'

const t = (key: string) => key

describe('displayLabel', () => {
  it('returns the raw value when present', () => {
    expect(displayLabel('/pricing', 'pages', t)).toBe('/pricing')
    expect(displayLabel('google.com', 'referrers', t)).toBe('google.com')
  })

  it('labels empty source / referrer values as direct', () => {
    expect(displayLabel('', 'sources', t)).toBe('pagesSources.direct')
    expect(displayLabel('', 'referrers', t)).toBe('pagesSources.direct')
  })

  it('labels empty page values as none', () => {
    expect(displayLabel('', 'pages', t)).toBe('pagesSources.none')
    expect(displayLabel('', 'entry', t)).toBe('pagesSources.none')
    expect(displayLabel('', 'exit', t)).toBe('pagesSources.none')
  })
})

describe('utmDisplayLabel', () => {
  it('returns the raw value when present', () => {
    expect(utmDisplayLabel('spring_sale', t)).toBe('spring_sale')
  })

  it('labels empty values as none', () => {
    expect(utmDisplayLabel('', t)).toBe('pagesSources.none')
  })
})
