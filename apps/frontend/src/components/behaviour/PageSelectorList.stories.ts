// =============================================================================
// PageSelectorList — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import PageSelectorList from './PageSelectorList.vue'

const rows = [
  { value: '/pricing', metrics: { events: 8203 } },
  { value: '/', metrics: { events: 6991 } },
  { value: '/docs', metrics: { events: 4812 } },
  { value: '/blog/launch', metrics: { events: 3110 } },
  { value: '/changelog', metrics: { events: 1280 } },
]

const meta: Meta<typeof PageSelectorList> = {
  title: 'Behaviour/PageSelectorList',
  component: PageSelectorList,
  tags: ['autodocs'],
  args: {
    rows,
    selected: '/pricing',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { rows: [] } }
