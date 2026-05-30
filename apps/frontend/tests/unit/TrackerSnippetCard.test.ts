// =============================================================================
// Settings — TrackerSnippetCard component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import TrackerSnippetCard from '@/components/settings/TrackerSnippetCard.vue'
import { datetimeFormatsEn, datetimeFormatsFr } from '@/i18n/datetimeFormats'
import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'
import type { Site } from '@/api/types'

// A dedicated instance registering datetime formats so `d(date, 'long')` works.
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en, fr },
  datetimeFormats: { en: datetimeFormatsEn, fr: datetimeFormatsFr },
})

const site: Site = {
  id: 's1',
  team_id: 't1',
  name: 'My Site',
  domain: 'example.com',
  tracker_key: 'stk_public_abc',
  timezone: 'UTC',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

function renderCard(props: Record<string, unknown> = {}) {
  return render(TrackerSnippetCard, {
    props: { site, origin: 'https://app.example.com', ...props },
    global: { plugins: [i18n] },
  })
}

describe('TrackerSnippetCard', () => {
  beforeEach(() => {
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    })
  })

  it('renders the snippet with the public key and operator origin', () => {
    renderCard()
    const pre = document.querySelector('pre')?.textContent ?? ''
    expect(pre).toContain('data-site-key="stk_public_abc"')
    expect(pre).toContain('https://app.example.com/sf/tracker.js')
  })

  it('shows the public key value', () => {
    renderCard()
    expect(screen.getByText('stk_public_abc')).toBeInTheDocument()
  })

  it('copies the snippet to the clipboard', async () => {
    renderCard()
    await fireEvent.click(screen.getByRole('button', { name: /Copy/i }))
    expect(navigator.clipboard.writeText).toHaveBeenCalledOnce()
    await waitFor(() => expect(screen.getByText(/Copied/i)).toBeInTheDocument())
  })

  it('shows the grace-period note after a rotation', () => {
    renderCard({ oldKeyValidUntil: '2026-06-01T00:00:00Z' })
    expect(screen.getByText(/previous key keeps working/i)).toBeInTheDocument()
  })

  it('opens the rotate confirmation and emits rotate on confirm', async () => {
    const { emitted } = renderCard()
    await fireEvent.click(screen.getByRole('button', { name: /Rotate key/i }))
    // After opening, the trigger and the dialog action both read "Rotate key";
    // confirm via the alertdialog so we exercise the confirmation path.
    const dialog = await screen.findByRole('alertdialog')
    const confirm = Array.from(dialog.querySelectorAll('button')).find((btn) =>
      /Rotate key/i.test(btn.textContent ?? ''),
    )
    await fireEvent.click(confirm as HTMLElement)
    expect(emitted().rotate).toBeTruthy()
  })

  it('hides rotation controls when readonly', () => {
    renderCard({ readonly: true })
    expect(screen.queryByRole('button', { name: /Rotate key/i })).toBeNull()
  })
})
