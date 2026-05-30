// =============================================================================
// PublicPasswordGate — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for PublicPasswordGate.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import PublicPasswordGate from '@/components/publicshared/PublicPasswordGate.vue'
import { testI18n } from '../setup'

function renderGate(props: Record<string, unknown> = {}) {
  return render(PublicPasswordGate, {
    props,
    global: { plugins: [testI18n] },
  })
}

describe('PublicPasswordGate — structure', () => {
  it('renders the title, subtitle and submit button', () => {
    renderGate()
    expect(screen.getByText('Password required')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'View dashboard' })).toBeInTheDocument()
  })

  it('exposes the password field with a label', () => {
    renderGate()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
  })

  it('labels the form region for assistive tech', () => {
    renderGate()
    expect(
      screen.getByRole('main', { name: 'Password-protected dashboard' }),
    ).toBeInTheDocument()
  })
})

describe('PublicPasswordGate — submission', () => {
  it('emits submit with the entered password', async () => {
    const { emitted } = renderGate()
    await fireEvent.update(screen.getByLabelText('Password'), 'hunter2')
    await fireEvent.click(screen.getByRole('button', { name: 'View dashboard' }))
    expect(emitted().submit).toBeTruthy()
    expect(emitted().submit[0]).toEqual(['hunter2'])
  })

  it('trims surrounding whitespace before emitting', async () => {
    const { emitted } = renderGate()
    await fireEvent.update(screen.getByLabelText('Password'), '  secret  ')
    await fireEvent.click(screen.getByRole('button', { name: 'View dashboard' }))
    expect(emitted().submit[0]).toEqual(['secret'])
  })

  it('does not emit submit for a blank password', async () => {
    const { emitted } = renderGate()
    await fireEvent.update(screen.getByLabelText('Password'), '   ')
    const form = screen.getByLabelText('Password').closest('form') as HTMLFormElement
    await fireEvent.submit(form)
    expect(emitted().submit).toBeUndefined()
  })

  it('disables the submit button when the field is empty', () => {
    renderGate()
    expect(screen.getByRole('button', { name: 'View dashboard' })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })
})

describe('PublicPasswordGate — states', () => {
  it('shows an inline error message when error is true', () => {
    renderGate({ error: true })
    expect(screen.getByRole('alert')).toHaveTextContent('Incorrect password. Please try again.')
  })

  it('marks the field invalid when error is true', () => {
    renderGate({ error: true })
    expect(screen.getByLabelText('Password')).toHaveAttribute('aria-invalid', 'true')
  })

  it('disables the password field while loading', async () => {
    renderGate({ loading: true })
    await fireEvent.update(screen.getByLabelText('Password'), 'x')
    expect(screen.getByLabelText('Password')).toBeDisabled()
  })
})

describe('PublicPasswordGate — reveal toggle', () => {
  it('starts masked and toggles to a text input', async () => {
    renderGate()
    const field = screen.getByLabelText('Password')
    expect(field).toHaveAttribute('type', 'password')

    await fireEvent.click(screen.getByRole('button', { name: 'Show password' }))
    expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'text')

    await fireEvent.click(screen.getByRole('button', { name: 'Hide password' }))
    expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password')
  })

  it('reflects the toggle state via aria-pressed', async () => {
    renderGate()
    const toggle = screen.getByRole('button', { name: 'Show password' })
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
    await fireEvent.click(toggle)
    expect(screen.getByRole('button', { name: 'Hide password' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
  })
})
