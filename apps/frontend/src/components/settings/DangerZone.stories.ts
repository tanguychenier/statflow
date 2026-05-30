// =============================================================================
// Settings — DangerZone Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import DangerZone from './DangerZone.vue'
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

const meta: Meta<typeof DangerZone> = {
  title: 'Settings/DangerZone',
  component: DangerZone,
  tags: ['autodocs'],
  args: { site },
}

export default meta
type Story = StoryObj<typeof meta>

export const Owner: Story = { args: { canReset: true, canDelete: true } }
export const AdminNoDelete: Story = { args: { canReset: true, canDelete: false } }
