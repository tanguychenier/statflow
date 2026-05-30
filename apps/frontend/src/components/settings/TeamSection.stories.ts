// =============================================================================
// Settings — TeamSection Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import TeamSection from './TeamSection.vue'
import type { TeamMember } from '@/api/types'

const members: TeamMember[] = [
  {
    id: 'm1',
    user_id: 'u1',
    email: 'owner@mysite.com',
    name: 'Grace Hopper',
    role: 'owner',
    status: 'active',
    joined_at: '2025-09-01T00:00:00Z',
  },
  {
    id: 'm2',
    user_id: 'u2',
    email: 'ada@mysite.com',
    name: 'Ada Lovelace',
    role: 'editor',
    status: 'active',
    joined_at: '2025-11-12T00:00:00Z',
  },
  {
    id: 'm3',
    user_id: 'u3',
    email: 'pending@mysite.com',
    name: 'Alan Turing',
    role: 'viewer',
    status: 'invited',
    joined_at: '2026-02-01T00:00:00Z',
  },
]

const meta: Meta<typeof TeamSection> = {
  title: 'Settings/TeamSection',
  component: TeamSection,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Manager: Story = { args: { members, canManage: true } }
export const ReadOnly: Story = { args: { members, canManage: false } }
export const Loading: Story = { args: { members: [], loading: true } }
export const Empty: Story = { args: { members: [], canManage: true } }
export const ErrorState: Story = { args: { members: [], error: true } }
