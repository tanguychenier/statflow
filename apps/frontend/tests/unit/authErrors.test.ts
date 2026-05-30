// =============================================================================
// authErrors — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { ApiError } from '@/api/ApiError'
import { authErrorMessageKey, authFieldErrors } from '@/components/auth/authErrors'

function apiError(overrides: Partial<ConstructorParameters<typeof ApiError>[0]> = {}) {
  return new ApiError({ status: 500, code: 'x', title: 'x', ...overrides })
}

describe('authErrorMessageKey', () => {
  it('maps a non-ApiError to the generic key', () => {
    expect(authErrorMessageKey(new Error('boom'), 'login')).toBe('auth.errors.generic')
    expect(authErrorMessageKey('nope', 'login')).toBe('auth.errors.generic')
  })

  it('maps a network failure (status 0)', () => {
    expect(authErrorMessageKey(ApiError.network('offline'), 'login')).toBe('auth.errors.network')
  })

  it('maps rate limiting', () => {
    expect(authErrorMessageKey(apiError({ status: 429 }), 'forgotPassword')).toBe(
      'auth.errors.rateLimited',
    )
  })

  it('maps a 422 to the validation banner', () => {
    expect(authErrorMessageKey(apiError({ status: 422 }), 'register')).toBe(
      'auth.errors.validationFailed',
    )
  })

  it('maps a register conflict to email-taken', () => {
    expect(authErrorMessageKey(apiError({ status: 409 }), 'register')).toBe('auth.errors.emailTaken')
  })

  it('does not treat a 409 on login as email-taken', () => {
    expect(authErrorMessageKey(apiError({ status: 409 }), 'login')).toBe('auth.errors.generic')
  })

  it('maps a 401 on login to invalid credentials', () => {
    expect(authErrorMessageKey(apiError({ status: 401 }), 'login')).toBe(
      'auth.errors.invalidCredentials',
    )
  })

  it('maps a 401 on reset to the invalid-token message', () => {
    expect(authErrorMessageKey(apiError({ status: 401 }), 'resetPassword')).toBe(
      'auth.errors.invalidResetToken',
    )
  })

  it('maps the invalid-reset-token type even for another operation', () => {
    const error = apiError({
      status: 401,
      code: 'https://statflow.io/errors/invalid-reset-token',
    })
    expect(authErrorMessageKey(error, 'login')).toBe('auth.errors.invalidResetToken')
  })

  it('falls back to generic for unmapped statuses', () => {
    expect(authErrorMessageKey(apiError({ status: 500 }), 'login')).toBe('auth.errors.generic')
  })
})

describe('authFieldErrors', () => {
  it('returns an empty map for non-ApiError', () => {
    expect(authFieldErrors(new Error('x'))).toEqual({})
  })

  it('returns an empty map for a non-422 ApiError', () => {
    expect(authFieldErrors(apiError({ status: 400 }))).toEqual({})
  })

  it('collects the first message per field from a 422', () => {
    const error = apiError({
      status: 422,
      fieldErrors: [
        { field: 'email', message: 'Already used' },
        { field: 'email', message: 'ignored duplicate' },
        { field: 'password', message: 'Too weak' },
        { message: 'no field — skipped' },
        { field: 'name' },
      ],
    })
    expect(authFieldErrors(error)).toEqual({ email: 'Already used', password: 'Too weak' })
  })
})
