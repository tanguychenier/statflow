// =============================================================================
// Settings — GeneralSection component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import GeneralSection from '@/components/settings/GeneralSection.vue'
import type { Site } from '@/api/types'
import { testI18n } from '../setup'

const site: Site = {
  id: 's1',
  team_id: 't1',
  name: 'My Site',
  domain: 'example.com',
  tracker_key: 'stk_abc',
  timezone: 'Europe/Paris',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

function renderSection(props: Record<string, unknown> = {}) {
  return render(GeneralSection, {
    props: { site, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('GeneralSection', () => {
  it('seeds the fields from the site', () => {
    renderSection()
    expect((screen.getByLabelText(/Site name/i) as HTMLInputElement).value).toBe('My Site')
    expect((screen.getByLabelText(/Domain/i) as HTMLInputElement).value).toBe('example.com')
  })

  it('keeps Save disabled until something changes', () => {
    renderSection()
    expect(screen.getByRole('button', { name: /Save changes/i })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('enables Save once a field is edited and emits a clean request', async () => {
    const { emitted } = renderSection()
    await fireEvent.update(screen.getByLabelText(/Site name/i), '  Renamed  ')
    const save = screen.getByRole('button', { name: /Save changes/i })
    expect(save).not.toHaveAttribute('aria-disabled')
    await fireEvent.click(save)
    expect(emitted().save?.[0]).toEqual([
      { name: 'Renamed', domain: 'example.com', timezone: 'Europe/Paris' },
    ])
  })

  it('blocks save and shows an error on an empty name', async () => {
    const { emitted } = renderSection()
    await fireEvent.update(screen.getByLabelText(/Site name/i), '')
    await fireEvent.update(screen.getByLabelText(/Domain/i), 'still.com')
    await fireEvent.click(screen.getByRole('button', { name: /Save changes/i }))
    expect(emitted().save).toBeUndefined()
    expect(screen.getByText(/Enter a site name/i)).toBeInTheDocument()
  })

  it('blocks save and shows an error on an invalid domain', async () => {
    const { emitted } = renderSection()
    await fireEvent.update(screen.getByLabelText(/Domain/i), 'not a domain')
    await fireEvent.click(screen.getByRole('button', { name: /Save changes/i }))
    expect(emitted().save).toBeUndefined()
    expect(screen.getByText(/valid domain/i)).toBeInTheDocument()
  })

  it('hides the save action and disables fields when readonly', () => {
    renderSection({ readonly: true })
    expect(screen.queryByRole('button', { name: /Save changes/i })).toBeNull()
    expect(screen.getByLabelText(/Site name/i)).toBeDisabled()
  })
})
