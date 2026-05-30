// =============================================================================
// FunnelSummaryStats — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import FunnelSummaryStats from './FunnelSummaryStats.vue'

const meta: Meta<typeof FunnelSummaryStats> = {
  title: 'Funnels/FunnelSummaryStats',
  component: FunnelSummaryStats,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  args: {
    summary: { totalEntered: 8402, overallConversionPct: 12.3, totalTimeToConvertSeconds: 272 },
  },
}

export const WithoutTiming: Story = {
  args: {
    summary: { totalEntered: 3120, overallConversionPct: 28.7, totalTimeToConvertSeconds: null },
  },
}

export const Loading: Story = {
  args: {
    summary: { totalEntered: 0, overallConversionPct: 0, totalTimeToConvertSeconds: null },
    loading: true,
  },
}
