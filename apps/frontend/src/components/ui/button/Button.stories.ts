// =============================================================================
// Button — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import Button from './Button.vue'

const meta: Meta<typeof Button> = {
  title: 'UI/Button',
  component: Button,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: 'select',
      options: ['default', 'secondary', 'ghost', 'outline', 'destructive', 'link'],
    },
    size: { control: 'select', options: ['xs', 'sm', 'default', 'lg', 'icon', 'icon-sm'] },
    loading: { control: 'boolean' },
    disabled: { control: 'boolean' },
  },
  args: { variant: 'default', size: 'default' },
  render: (args) => ({
    components: { Button },
    setup: () => ({ args }),
    template: '<Button v-bind="args">Button</Button>',
  }),
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const Secondary: Story = { args: { variant: 'secondary' } }
export const Ghost: Story = { args: { variant: 'ghost' } }
export const Outline: Story = { args: { variant: 'outline' } }
export const Destructive: Story = { args: { variant: 'destructive' } }
export const Link: Story = { args: { variant: 'link' } }
export const Loading: Story = { args: { loading: true } }
export const Disabled: Story = { args: { disabled: true } }

export const AllVariants: Story = {
  render: () => ({
    components: { Button },
    template: `
      <div style="display:flex;flex-wrap:wrap;gap:12px;">
        <Button variant="default">Default</Button>
        <Button variant="secondary">Secondary</Button>
        <Button variant="ghost">Ghost</Button>
        <Button variant="outline">Outline</Button>
        <Button variant="destructive">Destructive</Button>
        <Button variant="link">Link</Button>
      </div>
    `,
  }),
}
