// =============================================================================
// LoginView — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The auth-action layer, router and toast queue are mocked so the test focuses
// on the view's own behaviour: client-side validation gating, the success path
// (redirect honoured), and server-error handling (field markers + toast).
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { ApiError } from '@/api/ApiError'
import { testI18n } from '../setup'

const login = vi.fn()
const push = vi.fn().mockResolvedValue(undefined)
const toastError = vi.fn()
const toastSuccess = vi.fn()
const route = { query: {} as Record<string, unknown> }

vi.mock('@/components/auth/useAuthActions', () => ({
  useAuthActions: () => ({ login }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  useRoute: () => route,
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/composables/useToast', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccess(...args),
    error: (...args: unknown[]) => toastError(...args),
  },
  useToast: () => ({ toast: {} }),
}))

import LoginView from '@/views/auth/LoginView.vue'

function renderView() {
  return render(LoginView, { global: { plugins: [testI18n] } })
}

async function fillAndSubmit(email: string, password: string) {
  if (email) await fireEvent.update(screen.getByLabelText('Email'), email)
  if (password) await fireEvent.update(screen.getByLabelText('Password'), password)
  await fireEvent.click(screen.getByRole('button', { name: /sign in/i }))
}

describe('LoginView', () => {
  beforeEach(() => {
    login.mockReset()
    push.mockClear()
    toastError.mockClear()
    toastSuccess.mockClear()
    route.query = {}
  })

  it('blocks submission and shows validation errors when fields are empty', async () => {
    renderView()
    await fireEvent.click(screen.getByRole('button', { name: /sign in/i }))
    expect(login).not.toHaveBeenCalled()
    expect(screen.getByText('Email is required.')).toBeInTheDocument()
    expect(screen.getByText('Password is required.')).toBeInTheDocument()
  })

  it('flags a malformed email', async () => {
    renderView()
    await fillAndSubmit('not-an-email', 'whatever-password')
    expect(login).not.toHaveBeenCalled()
    expect(screen.getByText('Enter a valid email address.')).toBeInTheDocument()
  })

  it('signs in and redirects to the overview on success', async () => {
    login.mockResolvedValue(undefined)
    renderView()
    await fillAndSubmit('ada@example.com', 'a-valid-password')

    await waitFor(() => expect(login).toHaveBeenCalledWith({
      email: 'ada@example.com',
      password: 'a-valid-password',
    }))
    expect(toastSuccess).toHaveBeenCalled()
    await waitFor(() => expect(push).toHaveBeenCalledWith({ name: 'overview' }))
  })

  it('honours a redirect query parameter', async () => {
    login.mockResolvedValue(undefined)
    route.query = { redirect: '/funnels' }
    renderView()
    await fillAndSubmit('ada@example.com', 'a-valid-password')
    await waitFor(() => expect(push).toHaveBeenCalledWith('/funnels'))
  })

  it('shows an error toast and marks fields on a 401', async () => {
    login.mockRejectedValue(new ApiError({ status: 401, code: 'x', title: 'bad' }))
    renderView()
    await fillAndSubmit('ada@example.com', 'a-valid-password')
    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Incorrect email or password.'))
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
  })

  it('surfaces server field errors from a 422', async () => {
    login.mockRejectedValue(
      new ApiError({
        status: 422,
        code: 'x',
        title: 'bad',
        fieldErrors: [{ field: 'email', message: 'Unknown account' }],
      }),
    )
    renderView()
    await fillAndSubmit('ada@example.com', 'a-valid-password')
    await waitFor(() => expect(screen.getByText('Unknown account')).toBeInTheDocument())
  })

  it('clears a server field error once the user edits the field', async () => {
    login.mockRejectedValue(
      new ApiError({
        status: 422,
        code: 'x',
        title: 'bad',
        fieldErrors: [{ field: 'email', message: 'Unknown account' }],
      }),
    )
    renderView()
    await fillAndSubmit('ada@example.com', 'a-valid-password')
    await waitFor(() => expect(screen.getByText('Unknown account')).toBeInTheDocument())
    await fireEvent.update(screen.getByLabelText('Email'), 'ada2@example.com')
    expect(screen.queryByText('Unknown account')).not.toBeInTheDocument()
  })
})
