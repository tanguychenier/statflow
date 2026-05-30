// =============================================================================
// PagesSourcesTable — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import PagesSourcesTable from './PagesSourcesTable.vue'
import type { PagesSourcesRow } from './pagesSourcesTable'

function row(value: string, visitors: number, pageviews: number, bounce: number, dur: number, barPct: number): PagesSourcesRow {
  return {
    value,
    barPct,
    metrics: {
      visitors,
      pageviews,
      bounce_rate: bounce,
      avg_duration: dur,
      sessions: 0,
      events: 0,
      conversion_rate: 0,
    },
  }
}

const rows: PagesSourcesRow[] = [
  row('/pricing', 12043, 30210, 4210, 84, 100),
  row('/docs', 9812, 51200, 2840, 181, 81),
  row('/blog/launch-week', 7204, 9100, 5520, 72, 60),
  row('/', 6991, 14002, 3810, 96, 58),
  row('/pricing/pro', 4230, 5100, 2010, 142, 35),
]

const meta: Meta<typeof PagesSourcesTable> = {
  title: 'PagesSources/PagesSourcesTable',
  component: PagesSourcesTable,
  tags: ['autodocs'],
  args: {
    rows,
    tab: 'pages',
    dimensionHeader: 'Page',
    sort: { key: 'visitors', direction: 'desc' },
    page: 1,
    pageCount: 34,
    pageSize: 25,
    filteredCount: 842,
    totalCount: 842,
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Pages: Story = {}

export const Loading: Story = { args: { loading: true } }

export const Empty: Story = {
  args: { rows: [], filteredCount: 0, totalCount: 0, pageCount: 1 },
}

export const ErrorState: Story = { args: { error: true } }

export const Referrers: Story = {
  args: {
    tab: 'referrers',
    dimensionHeader: 'Referrer',
    rows: [
      row('google.com', 18204, 40210, 3110, 120, 100),
      row('', 12044, 30000, 4210, 84, 66),
      row('twitter.com', 4102, 6100, 5520, 48, 22),
    ],
    pageCount: 1,
    filteredCount: 3,
    totalCount: 3,
  },
}

export const Utm: Story = {
  args: {
    tab: 'utm',
    utm: true,
    dimensionHeader: 'UTM campaign',
    rows: [
      row('spring_sale', 5204, 9100, 3110, 90, 100),
      row('', 3044, 4000, 4210, 70, 58),
    ],
    pageCount: 1,
    filteredCount: 2,
    totalCount: 2,
  },
}
