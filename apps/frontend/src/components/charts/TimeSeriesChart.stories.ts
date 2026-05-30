// =============================================================================
// TimeSeriesChart — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import TimeSeriesChart from './TimeSeriesChart.vue'

const categories = Array.from({ length: 30 }, (_, i) => `2025-06-${String(i + 1).padStart(2, '0')}`)
const sessions = categories.map((_, i) => 4000 + Math.round(Math.sin(i / 3) * 1200 + i * 40))
const previous = sessions.map((v) => Math.round(v * 0.85))

const meta: Meta<typeof TimeSeriesChart> = {
  title: 'Charts/TimeSeriesChart',
  component: TimeSeriesChart,
  tags: ['autodocs'],
  args: {
    title: 'Sessions over time',
    categories,
    series: [{ name: 'Sessions', data: sessions }],
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const SingleSeries: Story = {}

export const WithComparison: Story = {
  args: {
    series: [
      { name: 'Sessions', data: sessions },
      { name: 'Previous period', data: previous, comparison: true },
    ],
  },
}

export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { series: [{ name: 'Sessions', data: categories.map(() => 0) }] } }
export const ErrorState: Story = { args: { error: true } }
