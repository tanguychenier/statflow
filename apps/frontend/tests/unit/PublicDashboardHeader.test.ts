// =============================================================================
// PublicDashboardHeader — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for PublicDashboardHeader.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import PublicDashboardHeader from '@/components/publicshared/PublicDashboardHeader.vue'
import { datetimeFormatsEn, datetimeFormatsFr } from '@/i18n/datetimeFormats'
import type { PublicPeriod } from '@/components/publicshared/publicPeriod'
import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'

// A dedicated instance carrying the datetime formats so `d(date, 'dateOnly')`
// resolves the named format rather than a brittle default.
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en, fr },
  datetimeFormats: { en: datetimeFormatsEn, fr: datetimeFormatsFr },
})

const period: PublicPeriod = {
  from: new Date(2024, 11, 1, 12),
  to: new Date(2024, 11, 31, 12),
}

function renderHeader(props: Record<string, unknown> = {}) {
  return render(PublicDashboardHeader, {
    props,
    global: { plugins: [i18n] },
  })
}

describe('PublicDashboardHeader — branding', () => {
  it('renders the Statflow brand mark', () => {
    renderHeader()
    expect(screen.getByText(/Statflow/)).toBeInTheDocument()
  })

  it('renders a "Powered by Statflow" link opening in a new tab', () => {
    renderHeader()
    const link = screen.getByRole('link', { name: /Powered by Statflow/i })
    expect(link).toHaveAttribute('href', 'https://statflow.io')
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
  })
})

describe('PublicDashboardHeader — title', () => {
  it('renders the generic title when no site label is given', () => {
    renderHeader()
    expect(screen.getByRole('heading', { name: 'Analytics' })).toBeInTheDocument()
  })

  it('interpolates the site label into the title', () => {
    renderHeader({ siteLabel: 'mysite.com' })
    expect(screen.getByRole('heading', { name: 'Analytics for mysite.com' })).toBeInTheDocument()
  })
})

describe('PublicDashboardHeader — period', () => {
  it('renders the formatted period range when a period is supplied', () => {
    renderHeader({ period })
    expect(screen.getByText(/Dec 1, 2024.*–.*Dec 31, 2024/)).toBeInTheDocument()
  })

  it('omits the period line when no period is supplied', () => {
    const { container } = renderHeader({ period: null })
    expect(container.querySelector('.public-header__period')).toBeNull()
  })

  it('shows a loading placeholder instead of the period while loading', () => {
    const { container } = renderHeader({ period, loading: true })
    expect(container.querySelector('.public-header__period--loading')).not.toBeNull()
    expect(screen.queryByText(/Dec 1, 2024/)).not.toBeInTheDocument()
  })
})
