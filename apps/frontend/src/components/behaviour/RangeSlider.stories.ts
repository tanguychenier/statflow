// =============================================================================
// RangeSlider — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import RangeSlider from './RangeSlider.vue'

const meta: Meta<typeof RangeSlider> = {
  title: 'Behaviour/RangeSlider',
  component: RangeSlider,
  tags: ['autodocs'],
  decorators: [
    (story) => ({
      components: { story },
      setup: () => ({}),
      template: '<div style="max-width: 320px"><story /></div>',
    }),
  ],
  args: {
    modelValue: 60,
    label: 'Intensity',
    min: 0,
    max: 100,
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Intensity: Story = {
  args: { label: 'Intensity', format: (v: number) => `${v}%` },
}

export const Radius: Story = {
  args: { label: 'Radius', modelValue: 30, min: 10, max: 80, format: (v: number) => `${v}px` },
}
