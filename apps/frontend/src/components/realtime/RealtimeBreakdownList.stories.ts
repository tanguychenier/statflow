// =============================================================================
// RealtimeBreakdownList — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import RealtimeBreakdownList from './RealtimeBreakdownList.vue'
import { countryFlag, countryName } from '@/components/overview/countries'
import type { RankedRow } from './realtimeModel'

const pages: RankedRow[] = [
  { key: '/pricing', label: '/pricing', value: 82, barPct: 100 },
  { key: '/', label: '/', value: 61, barPct: 74 },
  { key: '/docs', label: '/docs', value: 44, barPct: 54 },
  { key: '/blog/post-1', label: '/blog/post-1', value: 28, barPct: 34 },
]

const countries: RankedRow[] = [
  { key: 'US', label: countryName('US'), value: 82, barPct: 100 },
  { key: 'DE', label: countryName('DE'), value: 41, barPct: 50 },
  { key: 'FR', label: countryName('FR'), value: 38, barPct: 46 },
  { key: 'GB', label: countryName('GB'), value: 29, barPct: 35 },
  { key: 'BR', label: countryName('BR'), value: 18, barPct: 22 },
]

const meta: Meta<typeof RealtimeBreakdownList> = {
  title: 'Realtime/RealtimeBreakdownList',
  component: RealtimeBreakdownList,
  tags: ['autodocs'],
  args: {
    title: 'Top active pages',
    rows: pages,
    metricLabel: 'Active users',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const TopPages: Story = {}

export const Countries: Story = {
  args: {
    title: 'Countries',
    rows: countries,
    leadingFor: (row: RankedRow) => countryFlag(row.key),
  },
}

export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { rows: [] } }
export const ErrorState: Story = { args: { error: true, rows: [] } }
