// =============================================================================
// PasswordInput — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { testI18n } from '../setup'

function renderInput(props: Record<string, unknown> = {}) {
  return render(PasswordInput, {
    props: { modelValue: 'secret-value', ...props },
    global: { plugins: [testI18n] },
  })
}

describe('PasswordInput', () => {
  it('starts masked as a password field', () => {
    renderInput()
    const input = screen.getByDisplayValue('secret-value')
    expect(input).toHaveAttribute('type', 'password')
  })

  it('toggles to plain text and back', async () => {
    renderInput()
    const toggle = screen.getByTestId('password-toggle')
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
    expect(toggle).toHaveAccessibleName('Show password')

    await fireEvent.click(toggle)
    expect(screen.getByDisplayValue('secret-value')).toHaveAttribute('type', 'text')
    expect(toggle).toHaveAttribute('aria-pressed', 'true')
    expect(toggle).toHaveAccessibleName('Hide password')

    await fireEvent.click(toggle)
    expect(screen.getByDisplayValue('secret-value')).toHaveAttribute('type', 'password')
  })

  it('emits model updates as the user types', async () => {
    // A masked password input exposes no implicit ARIA role, so reveal it first
    // to obtain a queryable textbox, then drive an input event.
    const { emitted } = renderInput({ modelValue: '' })
    await fireEvent.click(screen.getByTestId('password-toggle'))
    await fireEvent.update(screen.getByRole('textbox'), 'typed-password')
    const events = emitted()['update:modelValue']
    expect(events?.at(-1)).toEqual(['typed-password'])
  })

  it('passes the autocomplete hint through to the field', () => {
    renderInput({ autocomplete: 'new-password' })
    expect(screen.getByDisplayValue('secret-value')).toHaveAttribute('autocomplete', 'new-password')
  })

  it('marks the field invalid and disables the toggle when disabled', () => {
    renderInput({ error: true, disabled: true })
    const input = screen.getByDisplayValue('secret-value')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toBeDisabled()
    expect(screen.getByTestId('password-toggle')).toBeDisabled()
  })

  it('forwards aria-describedby to the input', () => {
    renderInput({ describedBy: 'hint-1 error-1' })
    expect(screen.getByDisplayValue('secret-value')).toHaveAttribute(
      'aria-describedby',
      'hint-1 error-1',
    )
  })
})
