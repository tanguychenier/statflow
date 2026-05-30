// =============================================================================
// PagesSourcesToolbar — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for PagesSourcesToolbar.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import PagesSourcesToolbar from '@/components/pagessources/PagesSourcesToolbar.vue'
import { testI18n } from '../setup'

function renderToolbar(props: Record<string, unknown> = {}) {
  return render(PagesSourcesToolbar, {
    props: {
      search: '',
      searchPlaceholder: 'Search /path…',
      metric: 'visitors',
      ...props,
    },
    global: { plugins: [testI18n] },
  })
}

describe('PagesSourcesToolbar', () => {
  it('renders the search field with the provided placeholder', () => {
    renderToolbar()
    expect(screen.getByPlaceholderText('Search /path…')).toBeInTheDocument()
  })

  it('emits update:search as the user types', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.update(screen.getByPlaceholderText('Search /path…'), '/pricing')
    expect(emitted()['update:search'][0]).toEqual(['/pricing'])
  })

  it('emits submitSearch on Enter', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.keyDown(screen.getByPlaceholderText('Search /path…'), { key: 'Enter' })
    expect(emitted().submitSearch).toBeTruthy()
  })

  it('emits export when the export button is clicked', async () => {
    const { emitted } = renderToolbar()
    await fireEvent.click(screen.getByRole('button', { name: /Export/i }))
    expect(emitted().export).toBeTruthy()
  })

  it('shows a loading export button while exporting', () => {
    renderToolbar({ exporting: true })
    expect(screen.getByRole('button', { name: /Export/i })).toHaveAttribute('aria-busy', 'true')
  })

  it('renders the metric selector control', () => {
    renderToolbar()
    expect(screen.getByText('Metric')).toBeInTheDocument()
  })
})
