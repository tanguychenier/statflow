// =============================================================================
// PagesSourcesView — view composition tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The analytics composables and the ECharts-backed detail chart are mocked so
// the test exercises the view's composition: tab rendering, ?tab / ?utm URL
// sync, the UTM sub-tabs, the filter popover, and the row detail panel. Data
// shaping is covered by the pure-adapter tests. Coverage target: ≥90%.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, defineComponent, h } from 'vue'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import type { BreakdownResponse } from '@/api/types'

const breakdown: BreakdownResponse = {
  property: 'pathname',
  period: { from: '2024-12-01', to: '2024-12-31' },
  total_rows: 2,
  rows: [
    { value: '/pricing', metrics: { visitors: 120, pageviews: 300, bounce_rate: 4210, avg_duration: 84 } },
    { value: '/docs', metrics: { visitors: 90, pageviews: 500, bounce_rate: 2840, avg_duration: 181 } },
  ],
}

function fakeQuery<T>(data: T) {
  return {
    data: ref(data),
    isPending: ref(false),
    isError: ref(false),
    isFetching: ref(false),
    refetch: vi.fn(),
  }
}

vi.mock('@/api/composables/useAnalytics', () => ({
  useBreakdown: () => fakeQuery(breakdown),
  useTimeSeries: () =>
    fakeQuery({
      interval: 'day',
      period: { from: '2024-12-01', to: '2024-12-31' },
      series: [{ bucket: '2024-12-01', metrics: { visitors: 10 } }],
    }),
}))

import PagesSourcesView from '@/views/PagesSourcesView.vue'
import { testI18n } from '../setup'
import { useCurrentSiteStore } from '@/stores/currentSite'

const TimeSeriesStub = defineComponent({ setup: () => () => h('div', 'trend') })

async function renderView(initialPath = '/pages-sources'): Promise<{ router: Router }> {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/pages-sources', name: 'pages-sources', component: PagesSourcesView },
      { path: '/', name: 'home', component: { template: '<div />' } },
    ],
  })
  router.push(initialPath)
  await router.isReady()

  render(PagesSourcesView, {
    global: {
      plugins: [testI18n, router],
      stubs: { TimeSeriesChart: TimeSeriesStub },
    },
  })
  return { router }
}

describe('PagesSourcesView — tabs', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useCurrentSiteStore().selectSite('s1')
  })

  it('renders the screen title and the six tabs', async () => {
    await renderView()
    expect(screen.getByRole('heading', { name: 'Pages & Sources' })).toBeInTheDocument()
    for (const label of ['Pages', 'Entry pages', 'Exit pages', 'Sources', 'Referrers', 'UTM']) {
      expect(screen.getByRole('tab', { name: label })).toBeInTheDocument()
    }
  })

  it('defaults to the Pages tab and renders its table data', async () => {
    await renderView()
    expect(screen.getByText('/pricing')).toBeInTheDocument()
  })

  it('honours the ?tab query on load', async () => {
    await renderView('/pages-sources?tab=referrers')
    const tab = screen.getByRole('tab', { name: 'Referrers' })
    expect(tab).toHaveAttribute('aria-selected', 'true')
  })

  it('writes ?tab to the URL when a tab is selected', async () => {
    const { router } = await renderView()
    await fireEvent.click(screen.getByRole('tab', { name: 'Sources' }))
    await waitFor(() => expect(router.currentRoute.value.query.tab).toBe('sources'))
  })
})

describe('PagesSourcesView — UTM sub-tabs', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useCurrentSiteStore().selectSite('s1')
  })

  it('shows the three UTM sub-tabs on the UTM tab', async () => {
    await renderView('/pages-sources?tab=utm')
    const radios = screen.getAllByRole('radio')
    expect(radios.length).toBe(3)
  })

  it('writes ?utm when a sub-tab is chosen', async () => {
    const { router } = await renderView('/pages-sources?tab=utm')
    await fireEvent.click(screen.getByRole('radio', { name: 'UTM medium' }))
    await waitFor(() => expect(router.currentRoute.value.query.utm).toBe('utm_medium'))
  })

  it('drops the ?utm param when leaving the UTM tab', async () => {
    const { router } = await renderView('/pages-sources?tab=utm&utm=utm_medium')
    await fireEvent.click(screen.getByRole('tab', { name: 'Pages' }))
    await waitFor(() => expect(router.currentRoute.value.query.utm).toBeUndefined())
  })
})

describe('PagesSourcesView — filters & detail', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useCurrentSiteStore().selectSite('s1')
  })

  it('exposes the add-filter trigger', async () => {
    await renderView()
    expect(screen.getByRole('button', { name: /Add filter/ })).toBeInTheDocument()
  })

  it('opens the row detail panel on row click', async () => {
    await renderView()
    await fireEvent.click(screen.getByText('/pricing'))
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument())
  })
})
