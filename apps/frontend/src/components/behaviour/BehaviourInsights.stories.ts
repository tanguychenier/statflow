// =============================================================================
// BehaviourInsights — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import BehaviourInsights from './BehaviourInsights.vue'

const meta: Meta<typeof BehaviourInsights> = {
  title: 'Behaviour/BehaviourInsights',
  component: BehaviourInsights,
  tags: ['autodocs'],
  args: {
    rage: [
      { kind: 'rage', selector: '.checkout-submit', count: 412, sharePct: 54 },
      { kind: 'rage', selector: '.coupon-apply', count: 188, sharePct: 25 },
      { kind: 'rage', selector: '.qty-stepper', count: 96, sharePct: 13 },
    ],
    dead: [
      { kind: 'dead', selector: '.hero-banner', count: 233, sharePct: 48 },
      { kind: 'dead', selector: '.feature-card img', count: 142, sharePct: 29 },
    ],
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { rage: [], dead: [] } }
