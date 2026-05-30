// =============================================================================
// Settings — DangerZone + DangerConfirmDialog component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import DangerZone from '@/components/settings/DangerZone.vue'
import type { Site } from '@/api/types'
import { testI18n } from '../setup'

const site: Site = {
  id: 's1',
  team_id: 't1',
  name: 'My Site',
  domain: 'example.com',
  tracker_key: 'stk_abc',
  timezone: 'UTC',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

function renderZone(props: Record<string, unknown> = {}) {
  return render(DangerZone, {
    props: { site, canReset: true, canDelete: true, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('DangerZone', () => {
  it('hides the delete row when the viewer cannot delete', () => {
    renderZone({ canDelete: false })
    expect(screen.queryByRole('button', { name: /Delete site/i })).toBeNull()
    expect(screen.getByRole('button', { name: /Reset all data/i })).toBeInTheDocument()
  })

  it('hides the reset row when the viewer cannot reset', () => {
    renderZone({ canReset: false })
    expect(screen.queryByRole('button', { name: /Reset all data/i })).toBeNull()
  })

  // The confirm control lives inside the alertdialog; the trigger of the same
  // label stays in the card, so we always resolve buttons within the dialog.
  function dialogButton(label: RegExp): HTMLButtonElement {
    const dialog = screen.getByRole('alertdialog')
    const button = Array.from(dialog.querySelectorAll('button')).find((btn) =>
      label.test(btn.textContent ?? ''),
    )
    return button as HTMLButtonElement
  }

  it('keeps confirm disabled until the site name is typed exactly', async () => {
    renderZone()
    await fireEvent.click(screen.getByRole('button', { name: /Delete site/i }))
    await screen.findByRole('alertdialog')
    // Confirm is marked aria-disabled (a11y §3.3), not via the native attribute.
    expect(dialogButton(/Delete site/i).getAttribute('aria-disabled')).toBe('true')

    await fireEvent.update(screen.getByPlaceholderText('My Site'), 'wrong')
    expect(dialogButton(/Delete site/i).getAttribute('aria-disabled')).toBe('true')
  })

  it('does not emit delete while the confirm button is disabled', async () => {
    const { emitted } = renderZone()
    await fireEvent.click(screen.getByRole('button', { name: /Delete site/i }))
    await screen.findByRole('alertdialog')
    await fireEvent.update(screen.getByPlaceholderText('My Site'), 'wrong')
    await fireEvent.click(dialogButton(/Delete site/i))
    expect(emitted().delete).toBeUndefined()
  })

  it('emits delete only after an exact name match', async () => {
    const { emitted } = renderZone()
    await fireEvent.click(screen.getByRole('button', { name: /Delete site/i }))
    await screen.findByRole('alertdialog')
    await fireEvent.update(screen.getByPlaceholderText('My Site'), 'My Site')
    expect(dialogButton(/Delete site/i).hasAttribute('aria-disabled')).toBe(false)
    await fireEvent.click(dialogButton(/Delete site/i))
    expect(emitted().delete).toBeTruthy()
  })

  it('emits reset after confirming the reset dialog', async () => {
    const { emitted } = renderZone()
    await fireEvent.click(screen.getByRole('button', { name: /Reset all data/i }))
    await screen.findByRole('alertdialog')
    await fireEvent.update(screen.getByPlaceholderText('My Site'), 'My Site')
    await fireEvent.click(dialogButton(/Reset all data/i))
    expect(emitted().reset).toBeTruthy()
  })
})
