// =============================================================================
// publicShareState — unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for publicShareState.ts
// =============================================================================

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { ApiError } from '@/api'
import {
  classifyShareError,
  clearStoredSharePassword,
  readStoredSharePassword,
  sharePasswordStorageKey,
  storeSharePassword,
} from '@/components/publicshared/publicShareState'

function apiError(status: number): ApiError {
  return new ApiError({ status, code: `http-${status}`, title: 'x' })
}

describe('classifyShareError', () => {
  it('classifies a 401 as password-required', () => {
    expect(classifyShareError(apiError(401))).toBe('password-required')
  })

  it('classifies a 404 as not-found', () => {
    expect(classifyShareError(apiError(404))).toBe('not-found')
  })

  it('classifies other ApiError statuses as generic', () => {
    expect(classifyShareError(apiError(500))).toBe('generic')
    expect(classifyShareError(apiError(0))).toBe('generic')
    expect(classifyShareError(apiError(503))).toBe('generic')
  })

  it('classifies a non-ApiError thrown value as generic', () => {
    expect(classifyShareError(new Error('boom'))).toBe('generic')
    expect(classifyShareError('boom')).toBe('generic')
    expect(classifyShareError(null)).toBe('generic')
    expect(classifyShareError(undefined)).toBe('generic')
  })
})

describe('sharePasswordStorageKey', () => {
  it('scopes the key to the token', () => {
    expect(sharePasswordStorageKey('abc')).toBe('sf-share-pwd:abc')
    expect(sharePasswordStorageKey('abc')).not.toBe(sharePasswordStorageKey('def'))
  })
})

describe('share password persistence', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('round-trips a stored password for a token', () => {
    storeSharePassword('tok', 's3cret')
    expect(readStoredSharePassword('tok')).toBe('s3cret')
  })

  it('isolates passwords across tokens', () => {
    storeSharePassword('tok-a', 'aaa')
    storeSharePassword('tok-b', 'bbb')
    expect(readStoredSharePassword('tok-a')).toBe('aaa')
    expect(readStoredSharePassword('tok-b')).toBe('bbb')
  })

  it('returns null when no password is stored', () => {
    expect(readStoredSharePassword('missing')).toBeNull()
  })

  it('returns null for an empty token without touching storage', () => {
    const spy = vi.spyOn(sessionStorage, 'getItem')
    expect(readStoredSharePassword('')).toBeNull()
    expect(spy).not.toHaveBeenCalled()
    spy.mockRestore()
  })

  it('clears a stored password', () => {
    storeSharePassword('tok', 'secret')
    clearStoredSharePassword('tok')
    expect(readStoredSharePassword('tok')).toBeNull()
  })

  it('ignores store/clear for an empty token', () => {
    const setSpy = vi.spyOn(sessionStorage, 'setItem')
    const removeSpy = vi.spyOn(sessionStorage, 'removeItem')
    storeSharePassword('', 'x')
    clearStoredSharePassword('')
    expect(setSpy).not.toHaveBeenCalled()
    expect(removeSpy).not.toHaveBeenCalled()
    setSpy.mockRestore()
    removeSpy.mockRestore()
  })
})

describe('share password persistence — storage failures', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('reads null when sessionStorage.getItem throws', () => {
    vi.spyOn(sessionStorage, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    expect(readStoredSharePassword('tok')).toBeNull()
  })

  it('swallows a sessionStorage.setItem failure', () => {
    vi.spyOn(sessionStorage, 'setItem').mockImplementation(() => {
      throw new Error('quota')
    })
    expect(() => storeSharePassword('tok', 'x')).not.toThrow()
  })

  it('swallows a sessionStorage.removeItem failure', () => {
    vi.spyOn(sessionStorage, 'removeItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    expect(() => clearStoredSharePassword('tok')).not.toThrow()
  })
})
