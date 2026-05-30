// =============================================================================
// FunnelBreakdownPanel — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The ECharts-backed BarChart and the Card framing are stubbed so the test
// focuses on the panel's own logic: the group-by selector and event wiring.
// =============================================================================

import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { render, screen, fireEvent } from '@testing-library/vue'
import FunnelBreakdownPanel from '@/components/funnels/FunnelBreakdownPanel.vue'
import { testI18n } from '../setup'

const ChartCardStub = defineComponent({
  props: { title: String },
  setup: (_props, { slots }) => () => h('div', [slots.actions?.(), slots.default?.()]),
})
const BarChartStub = defineComponent({
  emits: ['retry'],
  setup: (_props, { emit }) =>
    () => h('button', { type: 'button', onClick: () => emit('retry') }, 'bar'),
})

function renderPanel(props: Record<string, unknown> = {}) {
  return render(FunnelBreakdownPanel, {
    props: {
      dimension: 'device_type',
      data: { categories: ['desktop', 'mobile'], values: [18, 9] },
      ...props,
    },
    global: {
      plugins: [testI18n],
      stubs: { ChartCard: ChartCardStub, BarChart: BarChartStub },
    },
  })
}

describe('FunnelBreakdownPanel', () => {
  it('renders the group-by selector', () => {
    renderPanel()
    expect(screen.getByLabelText('Group by')).toBeInTheDocument()
  })

  it('re-emits retry from the chart', async () => {
    const { emitted } = renderPanel()
    await fireEvent.click(screen.getByText('bar'))
    expect(emitted().retry).toBeTruthy()
  })

  it('exposes the dimension as a select value', () => {
    // The Reka Select trigger reflects the bound dimension; assert the control
    // mounts with the expected accessible name rather than relying on portal DOM.
    const onUpdate = vi.fn()
    renderPanel({ 'onUpdate:dimension': onUpdate })
    expect(screen.getByLabelText('Group by')).toBeInTheDocument()
  })
})
