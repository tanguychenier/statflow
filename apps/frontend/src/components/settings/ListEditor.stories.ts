// =============================================================================
// Settings — ListEditor Storybook stories
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { Meta, StoryObj } from '@storybook/vue3'
import ListEditor from './ListEditor.vue'
import { isValidDomainPattern, isValidIpOrCidr } from './settingsModel'

const meta: Meta<typeof ListEditor> = {
  title: 'Settings/ListEditor',
  component: ListEditor,
  tags: ['autodocs'],
  args: {
    addLabel: 'Add',
    removeLabel: 'Remove',
    emptyLabel: 'No entries yet.',
    invalidLabel: 'Some entries are invalid.',
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Ips: Story = {
  args: {
    modelValue: ['203.0.113.4', '10.0.0.0/8'],
    validate: isValidIpOrCidr,
    placeholder: '203.0.113.4 or 2001:db8::/32',
    inputAriaLabel: 'Excluded IP addresses',
  },
}

export const Domains: Story = {
  args: {
    modelValue: ['mysite.com', '*.mysite.com'],
    validate: isValidDomainPattern,
    placeholder: 'example.com',
    inputAriaLabel: 'Allowed domains',
  },
}

export const WithInvalidEntry: Story = {
  args: {
    modelValue: ['203.0.113.4', 'not-an-ip'],
    validate: isValidIpOrCidr,
    inputAriaLabel: 'Excluded IP addresses',
  },
}

export const Empty: Story = {
  args: { modelValue: [], validate: isValidIpOrCidr, inputAriaLabel: 'Excluded IP addresses' },
}
