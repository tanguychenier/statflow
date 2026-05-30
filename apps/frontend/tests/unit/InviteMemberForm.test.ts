// =============================================================================
// Settings — InviteMemberForm component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import InviteMemberForm from '@/components/settings/InviteMemberForm.vue'
import { testI18n } from '../setup'

function renderForm(props: Record<string, unknown> = {}) {
  return render(InviteMemberForm, { props, global: { plugins: [testI18n] } })
}

describe('InviteMemberForm', () => {
  it('rejects an invalid email and does not emit', async () => {
    const { emitted } = renderForm()
    await fireEvent.update(screen.getByLabelText(/Email address/i), 'not-an-email')
    await fireEvent.click(screen.getByRole('button', { name: /Send invite/i }))
    expect(emitted().invite).toBeUndefined()
    expect(screen.getByText(/valid email/i)).toBeInTheDocument()
  })

  it('emits a trimmed invite with the default viewer role', async () => {
    const { emitted } = renderForm()
    await fireEvent.update(screen.getByLabelText(/Email address/i), '  ada@example.com  ')
    await fireEvent.click(screen.getByRole('button', { name: /Send invite/i }))
    expect(emitted().invite?.[0]).toEqual([{ email: 'ada@example.com', role: 'viewer' }])
  })

  it('clears the field after a successful invite', async () => {
    renderForm()
    const input = screen.getByLabelText(/Email address/i) as HTMLInputElement
    await fireEvent.update(input, 'ada@example.com')
    await fireEvent.click(screen.getByRole('button', { name: /Send invite/i }))
    expect(input.value).toBe('')
  })
})
