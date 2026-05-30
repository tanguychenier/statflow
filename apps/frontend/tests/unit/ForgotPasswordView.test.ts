// =============================================================================
// ForgotPasswordView — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { ApiError } from '@/api/ApiError'
import { testI18n } from '../setup'

const forgotPassword = vi.fn()

vi.mock('@/components/auth/useAuthActions', () => ({
  useAuthActions: () => ({ forgotPassword }),
}))

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}))

import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue'

function renderView() {
  return render(ForgotPasswordView, { global: { plugins: [testI18n] } })
}

describe('ForgotPasswordView', () => {
  beforeEach(() => {
    forgotPassword.mockReset()
  })

  it('validates the email before sending', async () => {
    renderView()
    await fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))
    expect(forgotPassword).not.toHaveBeenCalled()
    expect(screen.getByText('Email is required.')).toBeInTheDocument()
  })

  it('shows the neutral confirmation with the submitted email on success', async () => {
    forgotPassword.mockResolvedValue(undefined)
    renderView()
    await fireEvent.update(screen.getByLabelText('Email'), 'ada@example.com')
    await fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))

    await waitFor(() => expect(screen.getByTestId('forgot-success')).toBeInTheDocument())
    expect(forgotPassword).toHaveBeenCalledWith({ email: 'ada@example.com' })
    expect(screen.getByText(/ada@example.com/)).toBeInTheDocument()
    // The confirmation is a polite live region for screen readers.
    expect(screen.getByTestId('forgot-success')).toHaveAttribute('aria-live', 'polite')
  })

  it('lets the user go back to the form to try another address', async () => {
    forgotPassword.mockResolvedValue(undefined)
    renderView()
    await fireEvent.update(screen.getByLabelText('Email'), 'ada@example.com')
    await fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))
    await waitFor(() => expect(screen.getByTestId('forgot-success')).toBeInTheDocument())

    await fireEvent.click(screen.getByRole('button', { name: /use a different email/i }))
    expect(screen.getByLabelText('Email')).toHaveValue('')
  })

  it('shows an inline error on a rate-limit failure', async () => {
    forgotPassword.mockRejectedValue(new ApiError({ status: 429, code: 'x', title: 'slow down' }))
    renderView()
    await fireEvent.update(screen.getByLabelText('Email'), 'ada@example.com')
    await fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))
    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Too many attempts. Please try again later.',
      ),
    )
    expect(screen.queryByTestId('forgot-success')).not.toBeInTheDocument()
  })
})
