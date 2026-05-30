// =============================================================================
// PublicDashboardHeader — Storybook Stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import PublicDashboardHeader from './PublicDashboardHeader.vue'

const period = {
  from: new Date(2024, 11, 1, 12),
  to: new Date(2024, 11, 31, 12),
}

const meta: Meta<typeof PublicDashboardHeader> = {
  title: 'PublicShared/PublicDashboardHeader',
  component: PublicDashboardHeader,
  tags: ['autodocs'],
  parameters: {
    docs: {
      description: {
        component:
          'Minimal header for the public shared dashboard (screens.md §8.1): logo, display-only period, and a "Powered by Statflow" attribution badge. No navigation or date picker.',
      },
    },
  },
  args: { period, siteLabel: null, loading: false },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const WithSiteLabel: Story = {
  name: 'With site label',
  args: { siteLabel: 'mysite.com' },
}

export const Loading: Story = { args: { loading: true } }

export const NoPeriod: Story = {
  name: 'No period',
  args: { period: null },
}
