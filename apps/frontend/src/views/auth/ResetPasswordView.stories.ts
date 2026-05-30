// =============================================================================
// ResetPasswordView — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import ResetPasswordView from './ResetPasswordView.vue'
import { withAuthProviders } from '@/components/auth/storyHarness'

const meta: Meta<typeof ResetPasswordView> = {
  title: 'Auth/Screens/ResetPassword',
  component: ResetPasswordView,
  tags: ['autodocs'],
  decorators: [withAuthProviders],
  parameters: { layout: 'fullscreen' },
}

export default meta
type Story = StoryObj<typeof meta>

// Valid link: the token is present in the query string.
export const WithToken: Story = {
  parameters: { initialRoute: '/reset-password?token=' + 'a'.repeat(40) },
}

export const French: Story = {
  globals: { locale: 'fr' },
  parameters: { initialRoute: '/reset-password?token=' + 'a'.repeat(40) },
}

// Recovery state shown when the link has no token.
export const MissingToken: Story = {
  parameters: { initialRoute: '/reset-password' },
}
