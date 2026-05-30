// =============================================================================
// Public shared dashboard — share-link state helpers
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Pure, framework-free helpers backing the public dashboard's gating logic
// (screens.md §8.3): classify the API failure into an actionable screen state,
// and persist a validated share password in sessionStorage so a refresh keeps
// the visitor in (and a closed tab forgets it). Isolated here so the view stays
// declarative and the branching is exhaustively unit-tested.
// =============================================================================

import { ApiError } from '@/api'

/**
 * How the screen should react to a failed public-overview fetch.
 *  - `password-required`: 401 — show (or re-show) the password gate.
 *  - `not-found`:         404 — the link is invalid, expired, or sharing is off.
 *  - `generic`:           anything else (network, 5xx) — show a retryable error.
 */
export type PublicShareErrorKind = 'password-required' | 'not-found' | 'generic'

/** Map an unknown thrown error to the screen state it should drive. */
export function classifyShareError(error: unknown): PublicShareErrorKind {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'password-required'
    if (error.status === 404) return 'not-found'
  }
  return 'generic'
}

/** sessionStorage key for a token's validated share password. Token-scoped so
 *  two share links open in the same session don't leak passwords to each other. */
export function sharePasswordStorageKey(token: string): string {
  return `sf-share-pwd:${token}`
}

/**
 * Read a previously validated password for this token from sessionStorage.
 * Returns null when absent or when storage is unavailable (private mode, SSR).
 */
export function readStoredSharePassword(token: string): string | null {
  if (!token) return null
  try {
    return sessionStorage.getItem(sharePasswordStorageKey(token))
  } catch {
    return null
  }
}

/** Persist a validated password for this token; silently no-ops if storage fails. */
export function storeSharePassword(token: string, password: string): void {
  if (!token) return
  try {
    sessionStorage.setItem(sharePasswordStorageKey(token), password)
  } catch {
    // Storage unavailable (private mode / quota) — the in-memory value still
    // gates the current view; only persistence across reloads is lost.
  }
}

/** Drop a stored password (e.g. it stopped working) for this token. */
export function clearStoredSharePassword(token: string): void {
  if (!token) return
  try {
    sessionStorage.removeItem(sharePasswordStorageKey(token))
  } catch {
    // No-op — see storeSharePassword.
  }
}
