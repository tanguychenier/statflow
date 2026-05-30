// =============================================================================
// PasswordInput — Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import { ref } from 'vue'
import PasswordInput from './PasswordInput.vue'
import { withAuthProviders } from './storyHarness'

const meta: Meta<typeof PasswordInput> = {
  title: 'Auth/PasswordInput',
  component: PasswordInput,
  tags: ['autodocs'],
  decorators: [
    withAuthProviders,
    (story) => ({ components: { story }, template: '<div style="max-width:336px"><story /></div>' }),
  ],
  render: (args) => ({
    components: { PasswordInput },
    setup() {
      const model = ref(args.modelValue ?? '')
      return { args, model }
    },
    template: '<PasswordInput v-bind="args" v-model="model" />',
  }),
}

export default meta
type Story = StoryObj<typeof meta>

export const Empty: Story = { args: { modelValue: '' } }
export const Filled: Story = { args: { modelValue: 'correct horse battery' } }
export const Error: Story = { args: { modelValue: 'short', error: true } }
export const Disabled: Story = { args: { modelValue: 'correct horse battery', disabled: true } }
