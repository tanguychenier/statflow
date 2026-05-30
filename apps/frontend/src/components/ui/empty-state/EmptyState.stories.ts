// =============================================================================
// EmptyState — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import EmptyState from './EmptyState.vue'

const meta: Meta<typeof EmptyState> = {
  title: 'UI/EmptyState',
  component: EmptyState,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: 'select',
      options: ['no-data', 'no-results', 'no-setup', 'no-access', 'error'],
    },
  },
  args: {
    title: 'No data for this period',
    description: 'Try adjusting your date range or filters.',
    variant: 'no-data',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const NoData: Story = {}
export const NoResults: Story = { args: { variant: 'no-results', title: 'No results found' } }
export const NoSetup: Story = {
  args: { variant: 'no-setup', title: 'No funnels yet', actionLabel: 'Create your first funnel' },
}
export const NoAccess: Story = { args: { variant: 'no-access', title: 'No access' } }
export const ErrorState: Story = {
  args: { variant: 'error', title: 'Failed to load data', actionLabel: 'Retry' },
}
