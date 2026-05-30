// =============================================================================
// authValidation — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  PASSWORD_MIN_LENGTH,
  allValid,
  validateEmail,
  validateLoginPassword,
  validateName,
  validateNewPassword,
  validatePasswordConfirmation,
  validateTermsAccepted,
} from '@/components/auth/authValidation'

describe('validateEmail', () => {
  it('rejects an empty value as required', () => {
    expect(validateEmail('')).toEqual({ valid: false, messageKey: 'auth.validation.emailRequired' })
  })

  it('treats whitespace-only as required', () => {
    expect(validateEmail('   ').messageKey).toBe('auth.validation.emailRequired')
  })

  it.each(['nope', 'a@b', 'a@b.', '@b.com', 'a b@c.com', 'a@@b.com'])(
    'rejects the malformed address %s',
    (value) => {
      expect(validateEmail(value)).toEqual({
        valid: false,
        messageKey: 'auth.validation.emailInvalid',
      })
    },
  )

  it('rejects an over-long address', () => {
    const longLocal = `${'a'.repeat(250)}@example.com`
    expect(validateEmail(longLocal).messageKey).toBe('auth.validation.emailInvalid')
  })

  it.each(['ada@example.com', 'a.b+tag@sub.domain.co'])('accepts the valid address %s', (value) => {
    expect(validateEmail(value)).toEqual({ valid: true })
  })

  it('trims surrounding whitespace before validating', () => {
    expect(validateEmail('  ada@example.com  ').valid).toBe(true)
  })
})

describe('validateName', () => {
  it('requires a non-empty name', () => {
    expect(validateName('  ')).toEqual({ valid: false, messageKey: 'auth.validation.nameRequired' })
  })

  it('accepts a real name', () => {
    expect(validateName('Ada Lovelace')).toEqual({ valid: true })
  })
})

describe('validateNewPassword', () => {
  it('flags an empty password as required', () => {
    expect(validateNewPassword('')).toEqual({
      valid: false,
      messageKey: 'auth.validation.passwordRequired',
    })
  })

  it('enforces the 12-character floor', () => {
    expect(validateNewPassword('a'.repeat(PASSWORD_MIN_LENGTH - 1))).toEqual({
      valid: false,
      messageKey: 'auth.validation.passwordTooShort',
    })
  })

  it('accepts a password at exactly the minimum length', () => {
    expect(validateNewPassword('a'.repeat(PASSWORD_MIN_LENGTH))).toEqual({ valid: true })
  })
})

describe('validateLoginPassword', () => {
  it('only requires non-emptiness', () => {
    expect(validateLoginPassword('')).toEqual({
      valid: false,
      messageKey: 'auth.validation.passwordRequired',
    })
    expect(validateLoginPassword('short')).toEqual({ valid: true })
  })
})

describe('validatePasswordConfirmation', () => {
  it('requires the confirmation field', () => {
    expect(validatePasswordConfirmation('secret', '')).toEqual({
      valid: false,
      messageKey: 'auth.validation.confirmRequired',
    })
  })

  it('rejects a mismatch', () => {
    expect(validatePasswordConfirmation('secret-one', 'secret-two')).toEqual({
      valid: false,
      messageKey: 'auth.validation.passwordMismatch',
    })
  })

  it('accepts a match', () => {
    expect(validatePasswordConfirmation('matching-pass', 'matching-pass')).toEqual({ valid: true })
  })
})

describe('validateTermsAccepted', () => {
  it('fails when unchecked', () => {
    expect(validateTermsAccepted(false)).toEqual({
      valid: false,
      messageKey: 'auth.validation.termsRequired',
    })
  })

  it('passes when checked', () => {
    expect(validateTermsAccepted(true)).toEqual({ valid: true })
  })
})

describe('allValid', () => {
  it('is true only when every result is valid', () => {
    expect(allValid({ valid: true }, { valid: true })).toBe(true)
    expect(allValid({ valid: true }, { valid: false, messageKey: 'x' })).toBe(false)
  })

  it('is vacuously true with no results', () => {
    expect(allValid()).toBe(true)
  })
})
