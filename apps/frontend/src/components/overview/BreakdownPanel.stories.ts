// =============================================================================
// BreakdownPanel — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import BreakdownPanel from './BreakdownPanel.vue'
import type { BreakdownListRow } from './breakdownTable'

const pageRows: BreakdownListRow[] = [
  { value: '/pricing', label: '/pricing', count: 12043, sharePct: 28.4, barPct: 100 },
  { value: '/docs', label: '/docs', count: 9812, sharePct: 23.1, barPct: 81 },
  { value: '/blog/post-1', label: '/blog/post-1', count: 7204, sharePct: 17.0, barPct: 60 },
  { value: '/', label: '/', count: 6991, sharePct: 16.5, barPct: 58 },
  { value: '/pricing/pro', label: '/pricing/pro', count: 4230, sharePct: 10.0, barPct: 35 },
]

const countryRows: BreakdownListRow[] = [
  { value: 'US', label: 'United States of America', count: 8203, sharePct: 38, barPct: 100 },
  { value: 'DE', label: 'Germany', count: 4100, sharePct: 19, barPct: 50 },
  { value: 'FR', label: 'France', count: 3800, sharePct: 18, barPct: 46 },
  { value: 'GB', label: 'United Kingdom', count: 2900, sharePct: 13, barPct: 35 },
  { value: 'BR', label: 'Brazil', count: 1800, sharePct: 8, barPct: 22 },
]

const meta: Meta<typeof BreakdownPanel> = {
  title: 'Overview/BreakdownPanel',
  component: BreakdownPanel,
  tags: ['autodocs'],
  args: {
    title: 'Top pages',
    rows: pageRows,
    metricLabel: 'Visitors',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const TopPages: Story = { args: { viewAllTo: '/pages-sources' } }

export const Countries: Story = {
  args: {
    title: 'Top countries',
    rows: countryRows,
    showFlag: true,
  },
}

export const Loading: Story = { args: { loading: true } }
export const Empty: Story = { args: { rows: [] } }
export const ErrorState: Story = { args: { error: true } }
