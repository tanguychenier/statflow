// =============================================================================
// Statflow Dashboard — Auth / session store
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Holds the in-memory access token and authentication state. The refresh token
// lives in an HttpOnly cookie (ADR-0009) and is never readable here — refresh
// works by posting to /auth/refresh with credentials included. The access token
// is deliberately kept in memory only (not localStorage) to limit XSS exposure.
// =============================================================================

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/endpoints/auth'
import type { LoginRequest, User } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const accessToken = ref<string | null>(null)
  const accessTokenExpiry = ref<number | null>(null)
  const user = ref<User | null>(null)
  /** A single in-flight refresh, shared by concurrent 401s to avoid a stampede. */
  const refreshInFlight = ref<Promise<string | null> | null>(null)
  /** The one-shot boot refresh; null until bootstrap() runs, then memoised. */
  const bootstrapPromise = ref<Promise<void> | null>(null)
  /** True while the boot-time silent refresh is resolving. */
  const bootstrapping = ref(false)

  const isAuthenticated = computed(() => accessToken.value !== null)

  function setSession(token: string, expiresInSeconds: number) {
    accessToken.value = token
    accessTokenExpiry.value = Date.now() + expiresInSeconds * 1000
  }

  function clearSession() {
    accessToken.value = null
    accessTokenExpiry.value = null
    user.value = null
  }

  function getAccessToken(): string | null {
    return accessToken.value
  }

  async function login(credentials: LoginRequest): Promise<void> {
    const response = await authApi.login(credentials)
    setSession(response.access_token, response.expires_in)
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      // Network errors during logout are expected (e.g. offline). Always clear
      // the local session regardless so the user is signed out client-side.
    } finally {
      clearSession()
    }
  }

  /**
   * Exchange the refresh cookie for a fresh access token. Concurrent callers
   * share the same promise so only one network round-trip happens. Returns the
   * new token on success or null when the session can no longer be renewed.
   */
  async function refresh(): Promise<string | null> {
    if (refreshInFlight.value) return refreshInFlight.value

    refreshInFlight.value = authApi
      .refresh()
      .then((response) => {
        setSession(response.access_token, response.expires_in)
        return response.access_token
      })
      .catch(() => {
        clearSession()
        return null
      })
      .finally(() => {
        refreshInFlight.value = null
      })

    return refreshInFlight.value
  }

  function setUser(next: User | null) {
    user.value = next
  }

  /**
   * Restore the session on a cold start (hard refresh). The access token only
   * ever lives in memory, so after a reload we must exchange the refresh cookie
   * for a fresh one before the router decides whether a protected route is
   * reachable. Runs at most once per app lifetime; a failed refresh is silent —
   * the user simply lands as an anonymous visitor.
   */
  function bootstrap(): Promise<void> {
    if (bootstrapPromise.value) return bootstrapPromise.value

    bootstrapping.value = true
    bootstrapPromise.value = refresh()
      .then(() => undefined)
      .finally(() => {
        bootstrapping.value = false
      })

    return bootstrapPromise.value
  }

  return {
    accessToken,
    accessTokenExpiry,
    user,
    bootstrapping,
    isAuthenticated,
    setSession,
    clearSession,
    getAccessToken,
    login,
    logout,
    refresh,
    bootstrap,
    setUser,
  }
})
