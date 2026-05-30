// =============================================================================
// AuthCard — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import AuthCard from './AuthCard.vue'
import { withAuthProviders } from './storyHarness'

const meta: Meta<typeof AuthCard> = {
  title: 'Auth/AuthCard',
  component: AuthCard,
  tags: ['autodocs'],
  decorators: [withAuthProviders],
  args: {
    title: 'Sign in to your account',
  },
  render: (args) => ({
    components: { AuthCard },
    setup: () => ({ args }),
    template: `
      <AuthCard v-bind="args">
        <p style="color: var(--sf-fg-secondary); font-size: 14px;">Form content goes here.</p>
        <template #footer>
          <p>No account? <a href="#" style="color: var(--sf-accent-text)">Create one</a></p>
        </template>
      </AuthCard>
    `,
  }),
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const WithSubtitle: Story = {
  args: { title: 'Reset your password', subtitle: "Enter your email and we'll send you a link." },
}
