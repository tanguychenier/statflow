// =============================================================================
// PagesSourcesToolbar — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import PagesSourcesToolbar from './PagesSourcesToolbar.vue'

const meta: Meta<typeof PagesSourcesToolbar> = {
  title: 'PagesSources/PagesSourcesToolbar',
  component: PagesSourcesToolbar,
  tags: ['autodocs'],
  args: {
    search: '',
    searchPlaceholder: 'Search /path…',
    metric: 'visitors',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const WithSearch: Story = { args: { search: '/pricing' } }

export const Exporting: Story = { args: { exporting: true } }

export const BounceRateMetric: Story = { args: { metric: 'bounce_rate' } }
