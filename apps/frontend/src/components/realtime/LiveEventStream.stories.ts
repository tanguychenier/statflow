// =============================================================================
// LiveEventStream — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import LiveEventStream from './LiveEventStream.vue'
import type { LiveEventRow } from './realtimeModel'

const NAMES = ['page_view', 'click', 'conversion', 'rage_click', 'page_view']
const PATHS = ['/pricing', '#cta-button', '/checkout', '/docs/api', '/blog/post-1']
const SOURCES = ['google.com', 'direct', 'twitter.com', 'direct', 'github.com']
const COUNTRIES = ['FR', 'US', 'GB', 'DE', 'BR']
const DEVICES = ['desktop', 'mobile', 'desktop', 'tablet', 'mobile']
const BROWSERS = ['Firefox', 'Chrome', 'Safari', 'Chrome', 'Edge']

const rows: LiveEventRow[] = Array.from({ length: 12 }, (_, i) => {
  const at = new Date(Date.parse('2026-05-29T12:04:38Z') - i * 1_000)
  const k = i % 5
  return {
    id: `e-${i}`,
    timestamp: at.toISOString(),
    eventName: NAMES[k],
    pathname: PATHS[k],
    source: SOURCES[k],
    country: COUNTRIES[k],
    device: DEVICES[k],
    browser: BROWSERS[k],
  }
})

const meta: Meta<typeof LiveEventStream> = {
  title: 'Realtime/LiveEventStream',
  component: LiveEventStream,
  tags: ['autodocs'],
  args: { rows },
}

export default meta
type Story = StoryObj<typeof meta>

export const Streaming: Story = {}
export const Paused: Story = { args: { paused: true, bufferedCount: 7 } }
export const Connecting: Story = { args: { rows: [], loading: true } }
export const Empty: Story = { args: { rows: [] } }
