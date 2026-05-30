// =============================================================================
// MetricCard — Storybook Stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import MetricCard from './MetricCard.vue'
import type { Trend } from '@/types'

const meta: Meta<typeof MetricCard> = {
  title: 'Components/MetricCard',
  component: MetricCard,
  tags: ['autodocs'],
  parameters: {
    docs: {
      description: {
        component:
          'KPI metric card with value display, trend badge (positive/negative/neutral), and sparkline placeholder. Supports a full loading skeleton state.',
      },
    },
  },
  argTypes: {
    title: { control: 'text', description: 'Visible card title' },
    value: { control: 'text', description: 'Formatted display value' },
    loading: { control: 'boolean', description: 'Loading skeleton state' },
    description: { control: 'text', description: 'Custom aria-label override' },
    trend: { control: 'object', description: 'Trend object (direction + magnitude)' },
  },
}

export default meta
type Story = StoryObj<typeof meta>

// ---------------------------------------------------------------------------
// Stories
// ---------------------------------------------------------------------------

export const Sessions: Story = {
  args: {
    title: 'Sessions',
    value: '84,203',
    trend: { value: 12.4, direction: 'up', positiveIsUp: true } satisfies Trend,
  },
}

export const WithSparkline: Story = {
  name: 'With Sparkline',
  args: {
    title: 'Sessions',
    value: '84,203',
    trend: { value: 12.4, direction: 'up', positiveIsUp: true } satisfies Trend,
    sparkData: Array.from({ length: 30 }, (_, i) => 2000 + Math.round(Math.sin(i / 4) * 500 + i * 30)),
  },
}

export const BounceRate: Story = {
  name: 'Bounce Rate (down = positive)',
  args: {
    title: 'Bounce rate',
    value: '42.1%',
    trend: { value: 3.2, direction: 'down', positiveIsUp: false } satisfies Trend,
  },
}

export const NegativeTrend: Story = {
  name: 'Negative Trend',
  args: {
    title: 'Avg. duration',
    value: '2m 04s',
    trend: { value: 8.7, direction: 'down', positiveIsUp: true } satisfies Trend,
  },
}

export const NeutralTrend: Story = {
  name: 'Neutral Trend',
  args: {
    title: 'Users',
    value: '61,412',
    trend: { value: 0, direction: 'neutral', positiveIsUp: true } satisfies Trend,
  },
}

export const NoTrend: Story = {
  name: 'No Trend',
  args: {
    title: 'Page views',
    value: '214,880',
  },
}

export const Loading: Story = {
  args: {
    title: 'Sessions',
    value: '—',
    loading: true,
  },
}

export const AllVariants: Story = {
  name: 'All Variants Grid',
  render: () => ({
    components: { MetricCard },
    setup() {
      const metrics = [
        {
          title: 'Sessions',
          value: '84,203',
          trend: { value: 12.4, direction: 'up', positiveIsUp: true } as Trend,
        },
        {
          title: 'Bounce rate',
          value: '42.1%',
          trend: { value: 3.2, direction: 'down', positiveIsUp: false } as Trend,
        },
        {
          title: 'Avg. duration',
          value: '2m 04s',
          trend: { value: 8.7, direction: 'down', positiveIsUp: true } as Trend,
        },
        {
          title: 'Page views',
          value: '214,880',
          trend: { value: 0, direction: 'neutral', positiveIsUp: true } as Trend,
        },
        { title: 'Loading…', value: '—', loading: true },
      ]
      return { metrics }
    },
    template: `
      <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; background: var(--sf-bg-base); padding: 24px;">
        <MetricCard
          v-for="(m, i) in metrics"
          :key="i"
          :title="m.title"
          :value="m.value"
          :trend="m.trend"
          :loading="m.loading"
        />
      </div>
    `,
  }),
}
