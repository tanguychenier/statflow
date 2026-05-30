// =============================================================================
// RegisterView — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import RegisterView from './RegisterView.vue'
import { withAuthProviders } from '@/components/auth/storyHarness'

const meta: Meta<typeof RegisterView> = {
  title: 'Auth/Screens/Register',
  component: RegisterView,
  tags: ['autodocs'],
  decorators: [withAuthProviders],
  parameters: { layout: 'fullscreen', initialRoute: '/register' },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}

export const French: Story = { globals: { locale: 'fr' } }
