// =============================================================================
// Settings — ListEditor component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import ListEditor from '@/components/settings/ListEditor.vue'
import { isValidIpOrCidr } from '@/components/settings/settingsModel'
import { testI18n } from '../setup'

function renderEditor(props: Record<string, unknown> = {}) {
  return render(ListEditor, {
    props: {
      modelValue: [],
      validate: isValidIpOrCidr,
      addLabel: 'Add',
      removeLabel: 'Remove',
      emptyLabel: 'Empty',
      invalidLabel: 'Invalid entries',
      inputAriaLabel: 'IPs',
      ...props,
    },
    global: { plugins: [testI18n] },
  })
}

describe('ListEditor', () => {
  it('shows the empty label when there are no entries', () => {
    renderEditor()
    expect(screen.getByText('Empty')).toBeInTheDocument()
  })

  it('renders existing entries as chips', () => {
    renderEditor({ modelValue: ['10.0.0.1', '10.0.0.2'] })
    expect(screen.getByText('10.0.0.1')).toBeInTheDocument()
    expect(screen.getByText('10.0.0.2')).toBeInTheDocument()
  })

  it('adds parsed entries on the Add button', async () => {
    const { emitted } = renderEditor()
    const input = screen.getByLabelText('IPs')
    await fireEvent.update(input, '10.0.0.1, 10.0.0.2')
    await fireEvent.click(screen.getByRole('button', { name: 'Add' }))
    expect(emitted()['update:modelValue']?.[0]).toEqual([['10.0.0.1', '10.0.0.2']])
  })

  it('adds on Enter and prevents form submission', async () => {
    const { emitted } = renderEditor()
    const input = screen.getByLabelText('IPs')
    await fireEvent.update(input, '10.0.0.9')
    await fireEvent.keyDown(input, { key: 'Enter' })
    expect(emitted()['update:modelValue']?.[0]).toEqual([['10.0.0.9']])
  })

  it('does not emit when the input parses to nothing', async () => {
    const { emitted } = renderEditor()
    const input = screen.getByLabelText('IPs')
    await fireEvent.update(input, '   ')
    await fireEvent.keyDown(input, { key: 'Enter' })
    expect(emitted()['update:modelValue']).toBeUndefined()
  })

  it('skips duplicates already present', async () => {
    const { emitted } = renderEditor({ modelValue: ['10.0.0.1'] })
    const input = screen.getByLabelText('IPs')
    await fireEvent.update(input, '10.0.0.1 10.0.0.2')
    await fireEvent.click(screen.getByRole('button', { name: 'Add' }))
    expect(emitted()['update:modelValue']?.[0]).toEqual([['10.0.0.1', '10.0.0.2']])
  })

  it('removes an entry via its remove button', async () => {
    const { emitted } = renderEditor({ modelValue: ['10.0.0.1', '10.0.0.2'] })
    await fireEvent.click(screen.getByRole('button', { name: 'Remove 10.0.0.1' }))
    expect(emitted()['update:modelValue']?.[0]).toEqual([['10.0.0.2']])
  })

  it('surfaces an alert when an entry fails validation', () => {
    renderEditor({ modelValue: ['not-an-ip'] })
    expect(screen.getByRole('alert')).toHaveTextContent('Invalid entries')
  })

  it('disables inputs and the add button when disabled', () => {
    renderEditor({ disabled: true, modelValue: ['10.0.0.1'] })
    expect(screen.getByLabelText('IPs')).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Add' })).toHaveAttribute('aria-disabled', 'true')
  })
})
