// =============================================================================
// Statflow Dashboard — Auth error → message-key mapping (Screen 7)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Translates an ApiError raised by the auth endpoints into a stable i18n key.
// The HTTP status is the primary signal (the contract in openapi.yaml is
// status-driven); the RFC 9457 `type` URI refines a couple of cases. Keeping
// this pure means every branch is unit-testable without a network or a store.
// =============================================================================

import { ApiError } from '@/api/ApiError'

/** Which auth operation produced the error — disambiguates a 401. */
export type AuthOperation = 'login' | 'register' | 'forgotPassword' | 'resetPassword'

const INVALID_RESET_TOKEN_TYPE = 'https://statflow.io/errors/invalid-reset-token'

/**
 * Map an unknown thrown value to an i18n key under `auth.errors.*`.
 *
 * Field-level 422 errors are surfaced inline by the forms; here we return the
 * generic "check the fields" banner key so the toast and the field markers do
 * not contradict each other.
 */
export function authErrorMessageKey(error: unknown, operation: AuthOperation): string {
  if (!(error instanceof ApiError)) return 'auth.errors.generic'

  if (error.status === 0) return 'auth.errors.network'
  if (error.status === 429) return 'auth.errors.rateLimited'
  if (error.status === 422) return 'auth.errors.validationFailed'

  if (error.status === 409 && operation === 'register') return 'auth.errors.emailTaken'

  if (error.status === 401) {
    if (operation === 'resetPassword' || error.code === INVALID_RESET_TOKEN_TYPE) {
      return 'auth.errors.invalidResetToken'
    }
    return 'auth.errors.invalidCredentials'
  }

  return 'auth.errors.generic'
}

/**
 * Extract a per-field error map from a 422 ProblemDetails payload, keyed by the
 * field name the backend reported (`email`, `password`, `name`, `new_password`).
 * Forms merge this over their client-side validation so server rules (e.g. an
 * email already normalised differently) still light up the right input.
 */
export function authFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.status !== 422) return {}
  const map: Record<string, string> = {}
  for (const fieldError of error.fieldErrors) {
    if (fieldError.field && fieldError.message && !(fieldError.field in map)) {
      map[fieldError.field] = fieldError.message
    }
  }
  return map
}
