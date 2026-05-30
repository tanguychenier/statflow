// =============================================================================
// FilterBuilder — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import FilterBuilder from '@/components/FilterBuilder/FilterBuilder.vue'
import type { Filter } from '@/api/types'
import { testI18n } from '../setup'

function renderBuilder(filters: Filter[]) {
  return render(FilterBuilder, {
    props: { filters, combination: 'and' },
    global: { plugins: [testI18n] },
  })
}

describe('FilterBuilder', () => {
  it('renders one row per condition', () => {
    const filters: Filter[] = [
      { property: 'pathname', operator: 'contains', value: '/blog' },
      { property: 'country', operator: 'eq', value: 'FR' },
    ]
    renderBuilder(filters)
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
  })

  it('emits a new condition with sensible defaults on add', async () => {
    const { emitted } = renderBuilder([])
    await fireEvent.click(screen.getByRole('button', { name: /condition/i }))
    const next = emitted<unknown[]>()['update:filters'][0][0] as Filter[]
    expect(next).toHaveLength(1)
    expect(next[0]).toMatchObject({ property: 'pathname', operator: 'eq' })
  })

  it('removes a condition', async () => {
    const filters: Filter[] = [{ property: 'pathname', operator: 'eq', value: '/' }]
    const { emitted } = renderBuilder(filters)
    await fireEvent.click(screen.getByRole('button', { name: /Remove/i }))
    expect(emitted<unknown[]>()['update:filters'][0][0]).toEqual([])
  })
})
