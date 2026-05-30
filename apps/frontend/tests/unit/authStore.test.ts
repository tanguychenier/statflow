// =============================================================================
// Statflow Dashboard — auth store tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/endpoints/auth'

describe('useAuthStore', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => vi.restoreAllMocks())

  it('starts unauthenticated', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.getAccessToken()).toBeNull()
  })

  it('logs in and stores the access token with an expiry', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({
      access_token: 'jwt',
      token_type: 'Bearer',
      expires_in: 900,
    })
    const store = useAuthStore()
    await store.login({ email: 'a@b.co', password: 'password1234' })
    expect(store.isAuthenticated).toBe(true)
    expect(store.getAccessToken()).toBe('jwt')
    expect(store.accessTokenExpiry).toBeGreaterThan(Date.now())
  })

  it('clears the session on logout even when the request fails', async () => {
    const store = useAuthStore()
    store.setSession('jwt', 900)
    vi.spyOn(authApi, 'logout').mockRejectedValue(new Error('network'))
    await store.logout()
    expect(store.isAuthenticated).toBe(false)
  })

  it('refreshes and returns the new token', async () => {
    vi.spyOn(authApi, 'refresh').mockResolvedValue({
      access_token: 'jwt2',
      token_type: 'Bearer',
      expires_in: 900,
    })
    const store = useAuthStore()
    const token = await store.refresh()
    expect(token).toBe('jwt2')
    expect(store.getAccessToken()).toBe('jwt2')
  })

  it('shares a single in-flight refresh between concurrent callers', async () => {
    const spy = vi.spyOn(authApi, 'refresh').mockResolvedValue({
      access_token: 'jwt3',
      token_type: 'Bearer',
      expires_in: 900,
    })
    const store = useAuthStore()
    const [a, b] = await Promise.all([store.refresh(), store.refresh()])
    expect(a).toBe('jwt3')
    expect(b).toBe('jwt3')
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('clears the session and returns null when refresh fails', async () => {
    vi.spyOn(authApi, 'refresh').mockRejectedValue(new Error('invalid'))
    const store = useAuthStore()
    store.setSession('old', 900)
    const token = await store.refresh()
    expect(token).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })

  it('bootstrap restores the session from the refresh cookie on a cold start', async () => {
    vi.spyOn(authApi, 'refresh').mockResolvedValue({
      access_token: 'restored',
      token_type: 'Bearer',
      expires_in: 900,
    })
    const store = useAuthStore()
    await store.bootstrap()
    expect(store.isAuthenticated).toBe(true)
    expect(store.getAccessToken()).toBe('restored')
    expect(store.bootstrapping).toBe(false)
  })

  it('bootstrap leaves the user anonymous when no session can be restored', async () => {
    vi.spyOn(authApi, 'refresh').mockRejectedValue(new Error('no cookie'))
    const store = useAuthStore()
    await store.bootstrap()
    expect(store.isAuthenticated).toBe(false)
    expect(store.bootstrapping).toBe(false)
  })

  it('bootstrap runs the refresh at most once even when called repeatedly', async () => {
    const spy = vi.spyOn(authApi, 'refresh').mockResolvedValue({
      access_token: 'restored',
      token_type: 'Bearer',
      expires_in: 900,
    })
    const store = useAuthStore()
    await Promise.all([store.bootstrap(), store.bootstrap()])
    await store.bootstrap()
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('flags bootstrapping while the boot refresh is in flight', async () => {
    let resolveRefresh: (value: { access_token: string; token_type: 'Bearer'; expires_in: number }) => void = () => {}
    vi.spyOn(authApi, 'refresh').mockReturnValue(
      new Promise((resolve) => {
        resolveRefresh = resolve
      }),
    )
    const store = useAuthStore()
    const pending = store.bootstrap()
    expect(store.bootstrapping).toBe(true)
    resolveRefresh({ access_token: 'restored', token_type: 'Bearer', expires_in: 900 })
    await pending
    expect(store.bootstrapping).toBe(false)
  })

  it('stores the current user profile', () => {
    const store = useAuthStore()
    store.setUser({
      id: 'u1',
      email: 'a@b.co',
      name: 'A',
      created_at: '',
      updated_at: '',
    })
    expect(store.user?.id).toBe('u1')
    store.setUser(null)
    expect(store.user).toBeNull()
  })
})
