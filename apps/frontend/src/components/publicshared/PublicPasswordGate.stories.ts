// =============================================================================
// PublicPasswordGate — Storybook Stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import PublicPasswordGate from './PublicPasswordGate.vue'

const meta: Meta<typeof PublicPasswordGate> = {
  title: 'PublicShared/PublicPasswordGate',
  component: PublicPasswordGate,
  tags: ['autodocs'],
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component:
          'Centred password prompt shown when a shared dashboard link is password-protected (screens.md §8.3). Emits `submit` with the entered password; the parent verifies it against the API.',
      },
    },
  },
  argTypes: {
    loading: { control: 'boolean', description: 'A verification attempt is in flight' },
    error: { control: 'boolean', description: 'The previous attempt was rejected' },
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = { args: {} }

export const Loading: Story = { args: { loading: true } }

export const Error: Story = {
  name: 'Wrong password',
  args: { error: true },
}
