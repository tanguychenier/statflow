// =============================================================================
// useRelativeTime — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { render } from '@testing-library/vue'
import { useRelativeTime } from '@/composables/useRelativeTime'
import { testI18n } from '../setup'

function capture(): { format: ReturnType<typeof useRelativeTime>['format'] } {
  const out: { format?: ReturnType<typeof useRelativeTime>['format'] } = {}
  const Host = defineComponent({
    setup() {
      out.format = useRelativeTime().format
      return () => null
    },
  })
  render(Host, { global: { plugins: [testI18n] } })
  return out as { format: ReturnType<typeof useRelativeTime>['format'] }
}

describe('useRelativeTime', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2025-06-15T12:00:00Z'))
  })
  afterEach(() => vi.useRealTimers())

  it('formats seconds-scale differences', () => {
    const { format } = capture()
    expect(format(new Date('2025-06-15T11:59:30Z'))).toMatch(/second|now/i)
  })

  it('formats minutes ago', () => {
    const { format } = capture()
    expect(format(new Date('2025-06-15T11:58:00Z'))).toContain('2 minutes ago')
  })

  it('formats hours ago', () => {
    const { format } = capture()
    expect(format(new Date('2025-06-15T09:00:00Z'))).toContain('3 hours ago')
  })

  it('formats days ago', () => {
    const { format } = capture()
    expect(format(new Date('2025-06-12T12:00:00Z'))).toContain('3 days ago')
  })

  it('accepts an ISO string input', () => {
    const { format } = capture()
    expect(format('2025-06-15T11:58:00Z')).toContain('2 minutes ago')
  })
})
