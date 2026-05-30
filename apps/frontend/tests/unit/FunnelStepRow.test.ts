// =============================================================================
// FunnelStepRow — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import FunnelStepRow from '@/components/funnels/FunnelStepRow.vue'
import { createDraftStep, type DraftStep } from '@/components/funnels/funnelBuilder'
import { testI18n } from '../setup'

function renderRow(overrides: Partial<DraftStep> = {}, props: Record<string, unknown> = {}) {
  const step: DraftStep = { ...createDraftStep(), ...overrides }
  return {
    step,
    ...render(FunnelStepRow, {
      props: {
        step,
        position: 1,
        canMoveUp: true,
        canMoveDown: true,
        canRemove: true,
        ...props,
      },
      global: { plugins: [testI18n] },
    }),
  }
}

describe('FunnelStepRow', () => {
  it('shows the 1-based position', () => {
    renderRow()
    expect(screen.getByText('1')).toBeInTheDocument()
  })

  it('emits a url patch for pageview steps', async () => {
    const { emitted } = renderRow({ triggerType: 'pageview' })
    const input = screen.getByLabelText('Match value for step 1')
    await fireEvent.update(input, '/checkout')
    expect(emitted<unknown[]>().update[0][0]).toEqual({ urlPattern: '/checkout' })
  })

  it('emits an event-name patch for event steps', async () => {
    const { emitted } = renderRow({ triggerType: 'event' })
    const input = screen.getByLabelText('Match value for step 1')
    await fireEvent.update(input, 'purchase')
    expect(emitted<unknown[]>().update[0][0]).toEqual({ eventName: 'purchase' })
  })

  it('emits a label patch', async () => {
    const { emitted } = renderRow()
    await fireEvent.update(screen.getByLabelText('Label for step 1'), 'View product')
    expect(emitted<unknown[]>().update[0][0]).toEqual({ label: 'View product' })
  })

  it('emits a trigger-type change from the segmented control', async () => {
    const { emitted } = renderRow({ triggerType: 'pageview' })
    await fireEvent.click(screen.getByRole('radio', { name: 'Event' }))
    expect(emitted<unknown[]>().update[0][0]).toEqual({ triggerType: 'event' })
  })

  it('emits move events', async () => {
    const { emitted } = renderRow()
    await fireEvent.click(screen.getByRole('button', { name: 'Move step up' }))
    await fireEvent.click(screen.getByRole('button', { name: 'Move step down' }))
    expect(emitted<unknown[]>().move[0][0]).toBe(-1)
    expect(emitted<unknown[]>().move[1][0]).toBe(1)
  })

  it('emits remove', async () => {
    const { emitted } = renderRow()
    await fireEvent.click(screen.getByRole('button', { name: 'Remove step 1' }))
    expect(emitted().remove).toBeTruthy()
  })

  it('disables reorder controls at the bounds', () => {
    renderRow({}, { canMoveUp: false, canMoveDown: false, canRemove: false })
    // Buttons mark disabled via aria-disabled (+ tabindex=-1) rather than the
    // native attribute, so they stay announced to screen readers (a11y §3.3).
    for (const name of ['Move step up', 'Move step down', 'Remove step 1']) {
      const button = screen.getByRole('button', { name })
      expect(button).toHaveAttribute('aria-disabled', 'true')
      expect(button).toHaveAttribute('tabindex', '-1')
    }
  })

  it('reveals the conditions builder on toggle', async () => {
    renderRow()
    const toggle = screen.getByRole('button', { name: /Conditions/i })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    await fireEvent.click(toggle)
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
  })

  it('starts expanded when the step already has filters', () => {
    renderRow({ filters: [{ property: 'country', operator: 'eq', value: 'FR' }] })
    expect(screen.getByRole('button', { name: /Conditions/i })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
  })
})
