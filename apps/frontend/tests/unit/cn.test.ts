// =============================================================================
// cn — class name utility tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { cn } from '@/lib/utils'

describe('cn', () => {
  it('joins truthy class names', () => {
    expect(cn('a', 'b')).toBe('a b')
  })

  it('drops falsy values', () => {
    expect(cn('a', false, undefined, null, 'b')).toBe('a b')
  })

  it('resolves conflicting tailwind utilities (last wins)', () => {
    expect(cn('px-2', 'px-4')).toBe('px-4')
  })

  it('supports conditional object syntax', () => {
    expect(cn({ 'is-on': true, 'is-off': false })).toBe('is-on')
  })
})
