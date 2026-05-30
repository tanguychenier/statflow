// =============================================================================
// FunnelStepRow — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import FunnelStepRow from './FunnelStepRow.vue'
import { createDraftStep } from './funnelBuilder'

const meta: Meta<typeof FunnelStepRow> = {
  title: 'Funnels/FunnelStepRow',
  component: FunnelStepRow,
  tags: ['autodocs'],
  args: {
    position: 1,
    canMoveUp: true,
    canMoveDown: true,
    canRemove: true,
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Pageview: Story = {
  args: {
    step: { ...createDraftStep('pageview'), label: 'View product', urlPattern: '/product' },
  },
}

export const Event: Story = {
  args: {
    step: { ...createDraftStep('event'), label: 'Purchase', eventName: 'purchase' },
  },
}

export const Invalid: Story = {
  args: {
    step: createDraftStep('pageview'),
    invalid: true,
  },
}

export const AtBounds: Story = {
  args: {
    step: { ...createDraftStep('pageview'), urlPattern: '/' },
    canMoveUp: false,
    canMoveDown: false,
    canRemove: false,
  },
}
