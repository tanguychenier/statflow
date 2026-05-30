// =============================================================================
// FunnelListItem — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import FunnelListItem from './FunnelListItem.vue'
import type { Funnel } from '@/api/types'

const funnel: Funnel = {
  id: 'f1',
  site_id: 's1',
  name: 'Checkout funnel',
  steps: [
    { step_index: 0, trigger_type: 'pageview', url_pattern: '/product' },
    { step_index: 1, trigger_type: 'pageview', url_pattern: '/cart' },
    { step_index: 2, trigger_type: 'pageview', url_pattern: '/checkout' },
    { step_index: 3, trigger_type: 'event', event_name: 'purchase' },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: new Date(Date.now() - 3 * 86_400_000).toISOString(),
}

const meta: Meta<typeof FunnelListItem> = {
  title: 'Funnels/FunnelListItem',
  component: FunnelListItem,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Queried: Story = {
  args: { entry: { funnel, stepCount: 4, overallConversionPct: 12.3 } },
}

export const NotYetQueried: Story = {
  args: { entry: { funnel, stepCount: 4, overallConversionPct: null } },
}
