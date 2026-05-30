// =============================================================================
// OverviewToolbar — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import OverviewToolbar from './OverviewToolbar.vue'

const meta: Meta<typeof OverviewToolbar> = {
  title: 'Overview/OverviewToolbar',
  component: OverviewToolbar,
  tags: ['autodocs'],
  args: {
    interval: 'day',
    compareEnabled: false,
    exporting: false,
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const Weekly: Story = { args: { interval: 'week' } }
export const Comparing: Story = { args: { compareEnabled: true } }
export const Exporting: Story = { args: { exporting: true } }
