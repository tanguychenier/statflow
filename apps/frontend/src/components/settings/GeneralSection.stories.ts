// =============================================================================
// Settings — GeneralSection Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import GeneralSection from './GeneralSection.vue'
import type { Site } from '@/api/types'

const site: Site = {
  id: 's1',
  team_id: 't1',
  name: 'My Website',
  domain: 'mysite.com',
  tracker_key: 'stk_live_abc123',
  timezone: 'Europe/Paris',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

const meta: Meta<typeof GeneralSection> = {
  title: 'Settings/GeneralSection',
  component: GeneralSection,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = { args: { site } }
export const Saving: Story = { args: { site, saving: true } }
export const ReadOnly: Story = { args: { site, readonly: true } }
