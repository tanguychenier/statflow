// =============================================================================
// FunnelBuilderModal — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import FunnelBuilderModal from './FunnelBuilderModal.vue'
import type { Funnel } from '@/api/types'

const funnel: Funnel = {
  id: 'f1',
  site_id: 's1',
  name: 'Checkout funnel',
  steps: [
    { step_index: 0, label: 'View product', trigger_type: 'pageview', url_pattern: '/product' },
    { step_index: 1, label: 'Add to cart', trigger_type: 'event', event_name: 'add_to_cart' },
    { step_index: 2, label: 'Purchase', trigger_type: 'event', event_name: 'purchase' },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-02T00:00:00Z',
}

const meta: Meta<typeof FunnelBuilderModal> = {
  title: 'Funnels/FunnelBuilderModal',
  component: FunnelBuilderModal,
  tags: ['autodocs'],
  parameters: { layout: 'fullscreen' },
}

export default meta
type Story = StoryObj<typeof meta>

export const Create: Story = {
  args: { open: true },
}

export const Edit: Story = {
  args: { open: true, funnel },
}

export const Saving: Story = {
  args: { open: true, funnel, saving: true },
}
