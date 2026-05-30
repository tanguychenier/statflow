// =============================================================================
// RealtimeKpiCard — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import RealtimeKpiCard from './RealtimeKpiCard.vue'

const meta: Meta<typeof RealtimeKpiCard> = {
  title: 'Realtime/RealtimeKpiCard',
  component: RealtimeKpiCard,
  tags: ['autodocs'],
  args: {
    label: 'Active users',
    value: '247',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const ActiveUsers: Story = { args: { pulse: true, live: true } }
export const TopPage: Story = { args: { label: 'Top page', value: '/pricing', mono: true } }
export const EventsPerMinute: Story = { args: { label: 'Events / min', value: '18' } }
export const MobileShare: Story = { args: { label: 'Mobile share', value: '31%' } }
export const Loading: Story = { args: { loading: true } }
