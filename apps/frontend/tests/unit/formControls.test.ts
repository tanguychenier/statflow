// =============================================================================
// Form controls — Input / Textarea / Switch / Checkbox / SegmentedControl
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { h } from 'vue'
import Input from '@/components/ui/input/Input.vue'
import Textarea from '@/components/ui/input/Textarea.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import FormField from '@/components/ui/input/FormField.vue'

describe('Input', () => {
  it('emits update:modelValue on input', async () => {
    const { emitted } = render(Input, { props: { modelValue: '' } })
    await fireEvent.update(screen.getByRole('textbox'), 'hello')
    expect(emitted()['update:modelValue'][0]).toEqual(['hello'])
  })

  it('marks aria-invalid when error', () => {
    render(Input, { props: { error: true } })
    expect(screen.getByRole('textbox').getAttribute('aria-invalid')).toBe('true')
  })

  it('renders a leading slot', () => {
    const { container } = render(Input, {
      slots: { leading: () => h('span', { class: 'lead' }, '@') },
    })
    expect(container.querySelector('.lead')).toBeInTheDocument()
  })
})

describe('Textarea', () => {
  it('emits update:modelValue on input', async () => {
    const { emitted } = render(Textarea, { props: { modelValue: '' } })
    await fireEvent.update(screen.getByRole('textbox'), 'multi\nline')
    expect(emitted()['update:modelValue'][0]).toEqual(['multi\nline'])
  })
})

describe('Switch', () => {
  it('emits the toggled value', async () => {
    const { emitted } = render(Switch, { props: { modelValue: false, ariaLabel: 'Toggle' } })
    await fireEvent.click(screen.getByRole('switch'))
    expect(emitted()['update:modelValue']).toBeTruthy()
  })
})

describe('Checkbox', () => {
  it('emits the toggled value', async () => {
    const { emitted } = render(Checkbox, { props: { modelValue: false, ariaLabel: 'Pick' } })
    await fireEvent.click(screen.getByRole('checkbox'))
    expect(emitted()['update:modelValue']).toBeTruthy()
  })
})

describe('SegmentedControl', () => {
  const options = [
    { value: 'table', label: 'Table' },
    { value: 'chart', label: 'Chart' },
  ]

  it('renders radio options and marks the active one', () => {
    render(SegmentedControl, { props: { modelValue: 'table', options, ariaLabel: 'View' } })
    const radios = screen.getAllByRole('radio')
    expect(radios).toHaveLength(2)
    expect(radios[0].getAttribute('aria-checked')).toBe('true')
  })

  it('emits the selected value', async () => {
    const { emitted } = render(SegmentedControl, {
      props: { modelValue: 'table', options, ariaLabel: 'View' },
    })
    await fireEvent.click(screen.getByRole('radio', { name: 'Chart' }))
    expect(emitted()['update:modelValue'][0]).toEqual(['chart'])
  })
})

describe('FormField', () => {
  it('wires the label, control id, and error message together', () => {
    const { container } = render(FormField, {
      props: { label: 'Email', required: true, error: 'Required' },
      slots: {
        default: (slotProps: { id: string; describedBy?: string; invalid: boolean }) =>
          h('input', {
            id: slotProps.id,
            'aria-describedby': slotProps.describedBy,
            'aria-invalid': slotProps.invalid,
          }),
      },
    })
    const input = container.querySelector('input') as HTMLInputElement
    const error = screen.getByRole('alert')
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(input.getAttribute('aria-describedby')).toContain(error.id)
  })

  it('shows the hint when there is no error', () => {
    render(FormField, {
      props: { label: 'Name', hint: 'Your display name' },
      slots: { default: () => h('input') },
    })
    expect(screen.getByText('Your display name')).toBeInTheDocument()
  })
})
