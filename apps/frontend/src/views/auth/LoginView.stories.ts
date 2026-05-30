// =============================================================================
// LoginView — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import LoginView from './LoginView.vue'
import { withAuthProviders } from '@/components/auth/storyHarness'

const meta: Meta<typeof LoginView> = {
  title: 'Auth/Screens/Login',
  component: LoginView,
  tags: ['autodocs'],
  decorators: [withAuthProviders],
  parameters: { layout: 'fullscreen', initialRoute: '/login' },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const French: Story = { globals: { locale: 'fr' } }
