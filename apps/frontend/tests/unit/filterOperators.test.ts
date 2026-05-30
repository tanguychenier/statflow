// =============================================================================
// Statflow Dashboard — filter builder operator manifest tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  DIMENSIONS,
  operatorsFor,
  isMultiValueOperator,
} from '@/components/FilterBuilder/operators'

describe('operatorsFor', () => {
  it('returns string operators for a string dimension', () => {
    const ops = operatorsFor('pathname')
    expect(ops).toContain('contains')
    expect(ops).toContain('starts_with')
  })

  it('falls back to string operators for an unknown dimension', () => {
    expect(operatorsFor('made_up')).toEqual(operatorsFor('pathname'))
  })
})

describe('isMultiValueOperator', () => {
  it('flags in / not_in as multi-value', () => {
    expect(isMultiValueOperator('in')).toBe(true)
    expect(isMultiValueOperator('not_in')).toBe(true)
  })

  it('treats single-value operators as scalar', () => {
    expect(isMultiValueOperator('eq')).toBe(false)
    expect(isMultiValueOperator('contains')).toBe(false)
  })
})

describe('DIMENSIONS', () => {
  it('matches the OpenAPI breakdown property list', () => {
    const properties = DIMENSIONS.map((d) => d.property)
    expect(properties).toContain('utm_source')
    expect(properties).toContain('country')
    expect(properties).toContain('device_type')
    expect(properties).toHaveLength(15)
  })
})
