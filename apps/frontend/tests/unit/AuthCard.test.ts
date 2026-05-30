// =============================================================================
// AuthCard — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { h } from 'vue'
import AuthCard from '@/components/auth/AuthCard.vue'
import { useThemeStore } from '@/stores/theme'
import { testI18n } from '../setup'

function renderCard(props: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
  return render(AuthCard, {
    props: { title: 'Sign in to your account', ...props },
    slots: { default: () => h('p', 'form'), ...slots },
    global: { plugins: [testI18n] },
  })
}

describe('AuthCard', () => {
  it('renders the brand mark and title', () => {
    renderCard()
    expect(screen.getByTestId('auth-brand')).toHaveTextContent('Statflow')
    expect(screen.getByRole('heading', { level: 1, name: 'Sign in to your account' })).toBeVisible()
  })

  it('labels the main landmark with the title', () => {
    renderCard()
    expect(screen.getByRole('main')).toHaveAccessibleName('Sign in to your account')
  })

  it('renders the subtitle only when provided', () => {
    const { rerender } = renderCard()
    expect(screen.queryByText('Reset hint')).not.toBeInTheDocument()
    return rerender({ title: 'x', subtitle: 'Reset hint' }).then(() => {
      expect(screen.getByText('Reset hint')).toBeVisible()
    })
  })

  it('renders the footer slot when present', () => {
    renderCard({}, { footer: () => h('span', 'footer-content') })
    expect(screen.getByText('footer-content')).toBeVisible()
  })

  it('toggles the theme store from the corner button', async () => {
    renderCard()
    const store = useThemeStore()
    expect(store.theme).toBe('dark')

    const toggle = screen.getByTestId('auth-theme-toggle')
    expect(toggle).toHaveAccessibleName('Switch to light theme')
    await fireEvent.click(toggle)
    expect(store.theme).toBe('light')
    expect(toggle).toHaveAccessibleName('Switch to dark theme')
  })
})
