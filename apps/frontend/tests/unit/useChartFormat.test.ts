// =============================================================================
// useChartFormat — composable tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { defineComponent } from 'vue'
import { render } from '@testing-library/vue'
import { useChartFormat } from '@/composables/useChartFormat'
import type { ChartFormatters } from '@/charts/options'
import { testI18n } from '../setup'

function capture(): ChartFormatters {
  const out: { fmt?: ChartFormatters } = {}
  const Host = defineComponent({
    setup() {
      const { formatters } = useChartFormat()
      out.fmt = formatters.value
      return () => null
    },
  })
  render(Host, { global: { plugins: [testI18n] } })
  return out.fmt as ChartFormatters
}

describe('useChartFormat', () => {
  it('formats values with compact notation', () => {
    const fmt = capture()
    expect(fmt.value(12000)).toMatch(/12K/i)
  })

  it('formats ISO date labels to a short month/day', () => {
    const fmt = capture()
    const label = fmt.axisDate?.('2025-06-15') ?? ''
    expect(label).toMatch(/Jun/)
  })

  it('passes through unparseable date labels unchanged', () => {
    const fmt = capture()
    expect(fmt.axisDate?.('not-a-date')).toBe('not-a-date')
  })
})
