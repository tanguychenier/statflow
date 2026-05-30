// =============================================================================
// useAuthActions — orchestration tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The endpoint client and the auth store are mocked: the test pins the wiring
// — login goes through the store (so the session is set), while register /
// forgot / reset call the stateless endpoints directly.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'

const storeLogin = vi.fn().mockResolvedValue(undefined)
const register = vi.fn().mockResolvedValue({ id: 'u1' })
const forgotPassword = vi.fn().mockResolvedValue(undefined)
const resetPassword = vi.fn().mockResolvedValue(undefined)

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ login: storeLogin }),
}))

vi.mock('@/api/endpoints/auth', () => ({
  authApi: {
    register: (...args: unknown[]) => register(...args),
    forgotPassword: (...args: unknown[]) => forgotPassword(...args),
    resetPassword: (...args: unknown[]) => resetPassword(...args),
  },
}))

import { useAuthActions } from '@/components/auth/useAuthActions'

describe('useAuthActions', () => {
  beforeEach(() => {
    storeLogin.mockClear()
    register.mockClear()
    forgotPassword.mockClear()
    resetPassword.mockClear()
  })

  it('routes login through the auth store', async () => {
    const { login } = useAuthActions()
    await login({ email: 'a@b.com', password: 'pw' })
    expect(storeLogin).toHaveBeenCalledWith({ email: 'a@b.com', password: 'pw' })
  })

  it('calls the register endpoint directly', async () => {
    const { register: doRegister } = useAuthActions()
    const body = { email: 'a@b.com', password: 'pw', name: 'Ada' }
    await doRegister(body)
    expect(register).toHaveBeenCalledWith(body)
  })

  it('calls forgot-password directly', async () => {
    const { forgotPassword: doForgot } = useAuthActions()
    await doForgot({ email: 'a@b.com' })
    expect(forgotPassword).toHaveBeenCalledWith({ email: 'a@b.com' })
  })

  it('calls reset-password directly', async () => {
    const { resetPassword: doReset } = useAuthActions()
    await doReset({ token: 't'.repeat(40), new_password: 'new-password!!' })
    expect(resetPassword).toHaveBeenCalledWith({ token: 't'.repeat(40), new_password: 'new-password!!' })
  })
})
