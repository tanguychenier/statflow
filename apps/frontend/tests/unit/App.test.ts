// =============================================================================
// App.vue — root shell tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Covers two plumbing behaviours that have no UI of their own: the document
// title reacting to a locale switch, and the boot-time loading state shown
// while the silent refresh is in flight.
// =============================================================================

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'

vi.mock('@/components/ui/toast', () => ({ Toaster: { template: '<div data-testid="toaster" />' } }))
vi.mock('@/components/CommandPalette', () => ({
  CommandPalette: { template: '<div data-testid="palette" />' },
}))

const route = { meta: { titleKey: 'nav.overview' } as { titleKey?: string } }
vi.mock('vue-router', () => ({
  useRoute: () => route,
}))

import App from '@/App.vue'

const RouterViewStub = { template: '<div data-testid="view" />' }

function mountApp() {
  const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en, fr } })
  const result = render(App, {
    global: {
      plugins: [i18n],
      components: { RouterView: RouterViewStub },
    },
  })
  return { i18n, ...result }
}

describe('App.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    route.meta = { titleKey: 'nav.overview' }
    document.title = ''
  })

  it('sets the document title from the active route on mount', () => {
    mountApp()
    expect(document.title).toBe('Overview — Statflow')
  })

  it('re-renders the document title when the locale changes', async () => {
    const { i18n } = mountApp()
    expect(document.title).toBe('Overview — Statflow')
    ;(i18n.global.locale as unknown as { value: string }).value = 'fr'
    await waitFor(() => expect(document.title).toBe("Vue d'ensemble — Statflow"))
  })

  it('shows the loading state while the boot refresh is in flight and hides the app', () => {
    const auth = useAuthStore()
    auth.bootstrapping = true
    mountApp()
    expect(screen.getByRole('status')).toHaveTextContent('Loading')
    expect(screen.queryByTestId('view')).not.toBeInTheDocument()
  })

  it('renders the app once the boot refresh has settled', async () => {
    const auth = useAuthStore()
    auth.bootstrapping = true
    mountApp()
    expect(screen.queryByTestId('view')).not.toBeInTheDocument()
    auth.bootstrapping = false
    await waitFor(() => expect(screen.getByTestId('view')).toBeInTheDocument())
  })
})
