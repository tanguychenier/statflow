// =============================================================================
// Settings — TrackingSection Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import TrackingSection from './TrackingSection.vue'
import type { SiteSettings } from '@/api/types'

const settings: SiteSettings = {
  excluded_ips: ['203.0.113.4', '10.0.0.0/8'],
  allowed_domains: ['mysite.com', '*.mysite.com'],
  data_retention_days: 365,
  strip_query_params: false,
  tracker_config: {
    track_clicks: true,
    track_scroll: true,
    track_engagement_time: true,
    track_outbound_links: true,
    hash_based_routing: false,
  },
}

const meta: Meta<typeof TrackingSection> = {
  title: 'Settings/TrackingSection',
  component: TrackingSection,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = { args: { settings } }
export const CustomRetention: Story = {
  args: { settings: { ...settings, data_retention_days: 120 } },
}
export const Empty: Story = { args: { settings: {} } }
export const ReadOnly: Story = { args: { settings, readonly: true } }
