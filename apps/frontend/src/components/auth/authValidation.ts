// =============================================================================
// Statflow Dashboard — Auth form validation (Screen 7)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Pure, framework-free validators for the auth forms. They return i18n keys
// (never user-facing strings) so the view layer owns translation. Keeping the
// rules here makes them unit-testable without mounting a component and keeps the
// 12-character password floor (ADR-0009 §2) defined in exactly one place.
// =============================================================================

/** Minimum password length, mandated by ADR-0009 for every auth form. */
export const PASSWORD_MIN_LENGTH = 12

/** Server-enforced caps mirrored from openapi.yaml so we fail fast client-side. */
export const EMAIL_MAX_LENGTH = 254
export const PASSWORD_MAX_LENGTH = 128
export const NAME_MAX_LENGTH = 100

/**
 * A pragmatic email shape check. The backend is the authority (RFC 5322 is not
 * worth re-implementing in the browser); this only catches obvious typos before
 * a round-trip — a single `@`, a non-empty local part, and a dotted domain.
 */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/** A validation result is either valid, or carries the failing i18n key. */
export interface FieldResult {
  valid: boolean
  messageKey?: string
}

const OK: FieldResult = { valid: true }

function fail(messageKey: string): FieldResult {
  return { valid: false, messageKey }
}

export function validateEmail(raw: string): FieldResult {
  const value = raw.trim()
  if (value.length === 0) return fail('auth.validation.emailRequired')
  if (value.length > EMAIL_MAX_LENGTH || !EMAIL_PATTERN.test(value)) {
    return fail('auth.validation.emailInvalid')
  }
  return OK
}

export function validateName(raw: string): FieldResult {
  const value = raw.trim()
  if (value.length === 0) return fail('auth.validation.nameRequired')
  return OK
}

/**
 * Password validity for *creating* a password (register, reset): the 12-char
 * floor applies. The empty case is reported as "required" so the message is
 * actionable rather than the length rule.
 */
export function validateNewPassword(value: string): FieldResult {
  if (value.length === 0) return fail('auth.validation.passwordRequired')
  if (value.length < PASSWORD_MIN_LENGTH) return fail('auth.validation.passwordTooShort')
  return OK
}

/**
 * Password validity for *login*: we only require non-emptiness. The length
 * floor is not re-stated on sign-in — an existing account governs that, and a
 * length hint there would leak nothing useful while annoying valid users.
 */
export function validateLoginPassword(value: string): FieldResult {
  if (value.length === 0) return fail('auth.validation.passwordRequired')
  return OK
}

export function validatePasswordConfirmation(
  password: string,
  confirmation: string,
): FieldResult {
  if (confirmation.length === 0) return fail('auth.validation.confirmRequired')
  if (confirmation !== password) return fail('auth.validation.passwordMismatch')
  return OK
}

export function validateTermsAccepted(accepted: boolean): FieldResult {
  return accepted ? OK : fail('auth.validation.termsRequired')
}

/** True only when every supplied result is valid. */
export function allValid(...results: FieldResult[]): boolean {
  return results.every((result) => result.valid)
}
