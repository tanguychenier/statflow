// =============================================================================
// RangeSlider — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: 100% lines + branches for RangeSlider.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import RangeSlider from '@/components/behaviour/RangeSlider.vue'

function renderSlider(props: Partial<InstanceType<typeof RangeSlider>['$props']> = {}) {
  return render(RangeSlider, { props: { modelValue: 50, label: 'Intensity', ...props } })
}

describe('RangeSlider', () => {
  it('renders the label and a slider role', () => {
    renderSlider()
    expect(screen.getByText('Intensity')).toBeInTheDocument()
    expect(screen.getByRole('slider')).toBeInTheDocument()
  })

  it('shows the raw value when no formatter is given', () => {
    renderSlider({ modelValue: 30 })
    const slider = screen.getByRole('slider') as HTMLInputElement
    expect(slider.value).toBe('30')
    expect(slider.getAttribute('aria-valuetext')).toBe('30')
  })

  it('applies the formatter to the readout and aria-valuetext', () => {
    renderSlider({ modelValue: 30, format: (v: number) => `${v}%` })
    const slider = screen.getByRole('slider')
    expect(slider.getAttribute('aria-valuetext')).toBe('30%')
    expect(screen.getByText('30%')).toBeInTheDocument()
  })

  it('emits a numeric update on input', async () => {
    const { emitted } = renderSlider()
    const slider = screen.getByRole('slider')
    await fireEvent.update(slider, '72')
    expect(emitted()['update:modelValue']?.[0]).toEqual([72])
  })

  it('honours min, max and step bounds', () => {
    renderSlider({ min: 10, max: 80, step: 5 })
    const slider = screen.getByRole('slider')
    expect(slider.getAttribute('min')).toBe('10')
    expect(slider.getAttribute('max')).toBe('80')
    expect(slider.getAttribute('step')).toBe('5')
  })

  it('uses sensible default bounds', () => {
    renderSlider()
    const slider = screen.getByRole('slider')
    expect(slider.getAttribute('min')).toBe('0')
    expect(slider.getAttribute('max')).toBe('100')
    expect(slider.getAttribute('step')).toBe('1')
  })
})
