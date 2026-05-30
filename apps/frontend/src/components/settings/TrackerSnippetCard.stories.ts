// =============================================================================
// Settings — TrackerSnippetCard Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import TrackerSnippetCard from './TrackerSnippetCard.vue'
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

const meta: Meta<typeof TrackerSnippetCard> = {
  title: 'Settings/TrackerSnippetCard',
  component: TrackerSnippetCard,
  tags: ['autodocs'],
  args: { site, origin: 'https://app.mysite.com' },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const AfterRotation: Story = {
  args: { oldKeyValidUntil: '2026-06-15T12:00:00Z' },
}
export const ReadOnly: Story = { args: { readonly: true } }
