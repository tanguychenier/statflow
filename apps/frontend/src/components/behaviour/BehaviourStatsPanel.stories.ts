// =============================================================================
// BehaviourStatsPanel — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import BehaviourStatsPanel from './BehaviourStatsPanel.vue'

const meta: Meta<typeof BehaviourStatsPanel> = {
  title: 'Behaviour/BehaviourStatsPanel',
  component: BehaviourStatsPanel,
  tags: ['autodocs'],
  decorators: [
    (story) => ({
      components: { story },
      setup: () => ({}),
      template: '<div style="max-width: 320px"><story /></div>',
    }),
  ],
  args: {
    totalEvents: 8203,
    uniqueUsers: 4012,
    sampleSize: 6841,
    topElements: [
      { selector: '.cta-button', count: 3120, sharePct: 38 },
      { selector: '.pricing-table', count: 1720, sharePct: 21 },
      { selector: 'nav a', count: 1150, sharePct: 14 },
      { selector: '.hero img', count: 656, sharePct: 8 },
    ],
    scrollDepth: [
      { depthPct: 100, sessionsPct: 92 },
      { depthPct: 75, sessionsPct: 71 },
      { depthPct: 50, sessionsPct: 55 },
      { depthPct: 25, sessionsPct: 38 },
    ],
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const Loading: Story = { args: { loading: true } }
export const NoUniqueUsers: Story = { args: { uniqueUsers: null } }
export const Empty: Story = { args: { topElements: [], scrollDepth: [] } }
