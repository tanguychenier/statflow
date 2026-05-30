// =============================================================================
// ResetPasswordView — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { ApiError } from '@/api/ApiError'
import { testI18n } from '../setup'

const resetPassword = vi.fn()
const push = vi.fn().mockResolvedValue(undefined)
const route = { query: {} as Record<string, unknown> }

vi.mock('@/components/auth/useAuthActions', () => ({
  useAuthActions: () => ({ resetPassword }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  useRoute: () => route,
  RouterLink: { template: '<a><slot /></a>' },
}))

import ResetPasswordView from '@/views/auth/ResetPasswordView.vue'

function renderView() {
  return render(ResetPasswordView, { global: { plugins: [testI18n] } })
}

const VALID_TOKEN = 'a'.repeat(40)

describe('ResetPasswordView', () => {
  beforeEach(() => {
    resetPassword.mockReset()
    push.mockClear()
    route.query = {}
  })

  it('shows the recovery state when the link has no token', () => {
    renderView()
    expect(screen.getByTestId('reset-missing-token')).toBeInTheDocument()
    expect(screen.queryByLabelText('New password')).not.toBeInTheDocument()
  })

  it('routes to forgot-password from the recovery state', async () => {
    renderView()
    await fireEvent.click(screen.getByRole('button', { name: /request a new link/i }))
    expect(push).toHaveBeenCalledWith({ name: 'forgot-password' })
  })

  it('validates the new password length with a token present', async () => {
    route.query = { token: VALID_TOKEN }
    renderView()
    await fireEvent.update(screen.getByLabelText('New password'), 'short')
    await fireEvent.update(screen.getByLabelText('Confirm new password'), 'short')
    await fireEvent.click(screen.getByRole('button', { name: /reset password/i }))
    expect(resetPassword).not.toHaveBeenCalled()
    expect(screen.getByText('Password must be at least 12 characters.')).toBeInTheDocument()
  })

  it('submits the token + new password and shows the success state', async () => {
    route.query = { token: VALID_TOKEN }
    resetPassword.mockResolvedValue(undefined)
    renderView()
    await fireEvent.update(screen.getByLabelText('New password'), 'a-brand-new-password')
    await fireEvent.update(screen.getByLabelText('Confirm new password'), 'a-brand-new-password')
    await fireEvent.click(screen.getByRole('button', { name: /reset password/i }))

    await waitFor(() => expect(screen.getByTestId('reset-success')).toBeInTheDocument())
    expect(resetPassword).toHaveBeenCalledWith({
      token: VALID_TOKEN,
      new_password: 'a-brand-new-password',
    })
  })

  it('navigates to login from the success state', async () => {
    route.query = { token: VALID_TOKEN }
    resetPassword.mockResolvedValue(undefined)
    renderView()
    await fireEvent.update(screen.getByLabelText('New password'), 'a-brand-new-password')
    await fireEvent.update(screen.getByLabelText('Confirm new password'), 'a-brand-new-password')
    await fireEvent.click(screen.getByRole('button', { name: /reset password/i }))
    await waitFor(() => expect(screen.getByTestId('reset-success')).toBeInTheDocument())

    await fireEvent.click(screen.getByRole('button', { name: /back to sign in/i }))
    expect(push).toHaveBeenCalledWith({ name: 'login' })
  })

  it('shows the invalid-token error when the backend rejects the token', async () => {
    route.query = { token: VALID_TOKEN }
    resetPassword.mockRejectedValue(new ApiError({ status: 401, code: 'x', title: 'bad token' }))
    renderView()
    await fireEvent.update(screen.getByLabelText('New password'), 'a-brand-new-password')
    await fireEvent.update(screen.getByLabelText('Confirm new password'), 'a-brand-new-password')
    await fireEvent.click(screen.getByRole('button', { name: /reset password/i }))
    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(
        'This reset link is invalid, expired, or already used.',
      ),
    )
    expect(screen.queryByTestId('reset-success')).not.toBeInTheDocument()
  })
})
