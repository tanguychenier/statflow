// =============================================================================
// RealtimeTrendChart — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import RealtimeTrendChart from './RealtimeTrendChart.vue'
import { buildTrend } from './realtimeModel'
import type { LiveEventRow } from './realtimeModel'

const now = Date.parse('2026-05-29T12:04:38Z')

// Synthesise ~30 minutes of events with a gentle wave so every bucket is filled.
const events: LiveEventRow[] = []
for (let minute = 0; minute < 30; minute += 1) {
  const count = 4 + Math.round(Math.sin(minute / 3) * 3 + 3)
  for (let i = 0; i < count; i += 1) {
    const ts = new Date(now - minute * 60_000 - i * 1_000)
    events.push({
      id: `${minute}-${i}`,
      timestamp: ts.toISOString(),
      eventName: 'page_view',
      pathname: '/',
      source: 'direct',
      country: 'US',
      device: 'desktop',
      browser: 'Chrome',
    })
  }
}

const meta: Meta<typeof RealtimeTrendChart> = {
  title: 'Realtime/RealtimeTrendChart',
  component: RealtimeTrendChart,
  tags: ['autodocs'],
  args: {
    title: 'Active users — 30-min trend',
    buckets: buildTrend(events, now),
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Trend: Story = {}
export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { buckets: buildTrend([], now) } }
export const ErrorState: Story = { args: { error: true } }
