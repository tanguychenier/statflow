// =============================================================================
// Badge — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import Badge from './Badge.vue'
import Chip from './Chip.vue'
import ProBadge from './ProBadge.vue'

const meta: Meta<typeof Badge> = {
  title: 'UI/Badge',
  component: Badge,
  tags: ['autodocs'],
}

export default meta
type Story = StoryObj<typeof meta>

export const Variants: Story = {
  render: () => ({
    components: { Badge },
    template: `
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <Badge variant="default">Default</Badge>
        <Badge variant="success">Success</Badge>
        <Badge variant="warning">Warning</Badge>
        <Badge variant="error">Error</Badge>
        <Badge variant="info">Info</Badge>
        <Badge variant="accent">Accent</Badge>
        <Badge variant="outline">Outline</Badge>
      </div>
    `,
  }),
}

export const FilterChip: Story = {
  render: () => ({
    components: { Chip },
    template: '<Chip label="Source = Google" />',
  }),
}

export const Pro: Story = {
  render: () => ({ components: { ProBadge }, template: '<ProBadge />' }),
}
