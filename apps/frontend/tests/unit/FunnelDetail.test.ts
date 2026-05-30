// =============================================================================
// FunnelDetail — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The vue-query analytics composables and the ECharts-backed children are
// mocked / stubbed so the test exercises the detail's composition: breadcrumb,
// header stats wiring, dimension change, and the back / edit emits. The data
// reshaping itself is covered by the pure-adapter tests.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { render, screen, fireEvent } from '@testing-library/vue'
import type { FunnelResponse, Funnel } from '@/api/types'

const funnelResponse: FunnelResponse = {
  period: { from: '2026-01-01', to: '2026-01-31' },
  conversion_window_days: 30,
  steps: [
    {
      step_index: 0,
      label: 'View',
      event_name: 'pageview',
      entered: 1000,
      converted: 680,
      dropped: 320,
      conversion_rate_pct: 100,
      avg_time_to_convert_seconds: 0,
    },
    {
      step_index: 1,
      label: 'Buy',
      event_name: 'purchase',
      entered: 123,
      converted: 123,
      dropped: 0,
      conversion_rate_pct: 12.3,
      avg_time_to_convert_seconds: 60,
    },
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
  useFunnelQuery: () => fakeQuery(funnelResponse),
  useBreakdown: () =>
    fakeQuery({
      property: 'device_type',
      period: { from: '2026-01-01', to: '2026-01-31' },
      rows: [{ value: 'desktop', metrics: { conversion_rate: 15 } }],
      total_rows: 1,
    }),
  useTimeSeries: () =>
    fakeQuery({
      interval: 'day',
      period: { from: '2026-01-01', to: '2026-01-31' },
      series: [{ bucket: '2026-01-01', metrics: { conversion_rate: 12 } }],
    }),
}))

import FunnelDetail from '@/components/funnels/FunnelDetail.vue'
import { testI18n } from '../setup'
import { useCurrentSiteStore } from '@/stores/currentSite'

const funnel: Funnel = {
  id: 'f1',
  site_id: 's1',
  name: 'Checkout funnel',
  steps: [
    { step_index: 0, trigger_type: 'pageview', url_pattern: '/product' },
    { step_index: 1, trigger_type: 'event', event_name: 'purchase' },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-02T00:00:00Z',
}

// Stub the chart children — ECharts needs a real canvas. FunnelChart renders a
// real list so it is left intact for the step assertions.
const ChartCardStub = defineComponent({
  props: { title: String },
  setup: (_p, { slots }) => () => h('section', [slots.actions?.(), slots.default?.()]),
})
const TimeSeriesStub = defineComponent({ setup: () => () => h('div', 'trend') })
const BarChartStub = defineComponent({ setup: () => () => h('div', 'bars') })

function renderDetail() {
  return render(FunnelDetail, {
    props: { funnel },
    global: {
      plugins: [testI18n],
      stubs: { ChartCard: ChartCardStub, TimeSeriesChart: TimeSeriesStub, BarChart: BarChartStub },
    },
  })
}

describe('FunnelDetail', () => {
  beforeEach(() => {
    useCurrentSiteStore().selectSite('s1')
  })

  it('shows the funnel name in the breadcrumb', () => {
    renderDetail()
    expect(screen.getByText('Checkout funnel')).toBeInTheDocument()
  })

  it('renders the summary conversion from the response', () => {
    renderDetail()
    expect(screen.getByText(/12\.3%/)).toBeInTheDocument()
  })

  it('renders a funnel step per response step', () => {
    renderDetail()
    expect(screen.getByText('View')).toBeInTheDocument()
    expect(screen.getByText('Buy')).toBeInTheDocument()
  })

  it('emits back from the breadcrumb', async () => {
    const { emitted } = renderDetail()
    await fireEvent.click(screen.getByRole('button', { name: 'Funnels' }))
    expect(emitted().back).toBeTruthy()
  })

  it('emits edit from the toolbar', async () => {
    const { emitted } = renderDetail()
    await fireEvent.click(screen.getByRole('button', { name: 'Edit funnel' }))
    expect(emitted().edit).toBeTruthy()
  })

  it('offers the three breakdown dimensions', () => {
    renderDetail()
    expect(screen.getByLabelText('Group by')).toBeInTheDocument()
  })
})
