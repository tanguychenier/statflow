// =============================================================================
// RegisterView — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { ApiError } from '@/api/ApiError'
import { testI18n } from '../setup'

const register = vi.fn()
const login = vi.fn()
const push = vi.fn().mockResolvedValue(undefined)
const toastError = vi.fn()
const toastSuccess = vi.fn()

vi.mock('@/components/auth/useAuthActions', () => ({
  useAuthActions: () => ({ register, login }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/composables/useToast', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccess(...args),
    error: (...args: unknown[]) => toastError(...args),
  },
  useToast: () => ({ toast: {} }),
}))

import RegisterView from '@/views/auth/RegisterView.vue'

function renderView() {
  return render(RegisterView, { global: { plugins: [testI18n] } })
}

const VALID = {
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  password: 'a-strong-passphrase',
}

async function fillValid() {
  await fireEvent.update(screen.getByLabelText('Full name'), VALID.name)
  await fireEvent.update(screen.getByLabelText('Email'), VALID.email)
  await fireEvent.update(screen.getByLabelText('Password'), VALID.password)
  await fireEvent.update(screen.getByLabelText('Confirm password'), VALID.password)
  await fireEvent.click(screen.getByRole('checkbox'))
}

function submit() {
  return fireEvent.click(screen.getByRole('button', { name: /create account/i }))
}

describe('RegisterView', () => {
  beforeEach(() => {
    register.mockReset()
    login.mockReset()
    push.mockClear()
    toastError.mockClear()
    toastSuccess.mockClear()
  })

  it('reports every empty required field on submit', async () => {
    renderView()
    await submit()
    expect(register).not.toHaveBeenCalled()
    expect(screen.getByText('Name is required.')).toBeInTheDocument()
    expect(screen.getByText('Email is required.')).toBeInTheDocument()
    expect(screen.getByText('Password is required.')).toBeInTheDocument()
    expect(screen.getByText('Please confirm your password.')).toBeInTheDocument()
    expect(screen.getByText('You must accept the terms to continue.')).toBeInTheDocument()
  })

  it('enforces the 12-character password minimum', async () => {
    renderView()
    await fireEvent.update(screen.getByLabelText('Full name'), VALID.name)
    await fireEvent.update(screen.getByLabelText('Email'), VALID.email)
    await fireEvent.update(screen.getByLabelText('Password'), 'short')
    await fireEvent.update(screen.getByLabelText('Confirm password'), 'short')
    await fireEvent.click(screen.getByRole('checkbox'))
    await submit()
    expect(register).not.toHaveBeenCalled()
    expect(screen.getByText('Password must be at least 12 characters.')).toBeInTheDocument()
  })

  it('rejects mismatched confirmation', async () => {
    renderView()
    await fireEvent.update(screen.getByLabelText('Full name'), VALID.name)
    await fireEvent.update(screen.getByLabelText('Email'), VALID.email)
    await fireEvent.update(screen.getByLabelText('Password'), VALID.password)
    await fireEvent.update(screen.getByLabelText('Confirm password'), 'different-passphrase')
    await fireEvent.click(screen.getByRole('checkbox'))
    await submit()
    expect(register).not.toHaveBeenCalled()
    expect(screen.getByText('Passwords do not match.')).toBeInTheDocument()
  })

  it('registers, signs in and redirects on success', async () => {
    register.mockResolvedValue({ id: 'u1' })
    login.mockResolvedValue(undefined)
    renderView()
    await fillValid()
    await submit()

    await waitFor(() =>
      expect(register).toHaveBeenCalledWith({
        name: VALID.name,
        email: VALID.email,
        password: VALID.password,
      }),
    )
    expect(login).toHaveBeenCalledWith({ email: VALID.email, password: VALID.password })
    expect(toastSuccess).toHaveBeenCalled()
    await waitFor(() => expect(push).toHaveBeenCalledWith({ name: 'overview' }))
  })

  it('shows the email-taken error on a 409 and never signs in', async () => {
    register.mockRejectedValue(new ApiError({ status: 409, code: 'x', title: 'conflict' }))
    renderView()
    await fillValid()
    await submit()
    await waitFor(() =>
      expect(toastError).toHaveBeenCalledWith('An account with this email already exists.'),
    )
    expect(login).not.toHaveBeenCalled()
    expect(push).not.toHaveBeenCalled()
  })
})
