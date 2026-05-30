// =============================================================================
// ForgotPasswordView — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import { within, userEvent, waitFor, expect } from '@storybook/test'
import ForgotPasswordView from './ForgotPasswordView.vue'
import { withAuthProviders } from '@/components/auth/storyHarness'

const meta: Meta<typeof ForgotPasswordView> = {
  title: 'Auth/Screens/ForgotPassword',
  component: ForgotPasswordView,
  tags: ['autodocs'],
  decorators: [withAuthProviders],
  parameters: { layout: 'fullscreen', initialRoute: '/forgot-password' },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const French: Story = { globals: { locale: 'fr' } }

// Drives the form to the success confirmation so the "check your inbox" state
// is captured for visual review and axe scanning.
export const Submitted: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.type(canvas.getByLabelText('Email'), 'ada@example.com')
    await userEvent.click(canvas.getByRole('button', { name: /send reset link/i }))
    await waitFor(() => expect(canvas.getByTestId('forgot-success')).toBeInTheDocument())
  },
}
