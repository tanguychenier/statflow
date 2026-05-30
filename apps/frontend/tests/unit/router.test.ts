// =============================================================================
// Router — route table + auth guard tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const refresh = vi.fn()
vi.mock('@/api/endpoints/auth', () => ({
  authApi: {
    refresh: (...args: unknown[]) => refresh(...args),
  },
}))

import router from '@/router'
import { useAuthStore } from '@/stores/auth'

function routeNames(): string[] {
  const collect = (routes: typeof router.options.routes): string[] =>
    routes.flatMap((route) => [
      ...(route.name ? [String(route.name)] : []),
      ...(route.children ? collect(route.children) : []),
    ])
  return collect(router.options.routes)
}

describe('router route table', () => {
  it('declares every screen route agents will fill', () => {
    const names = routeNames()
    for (const name of [
      'login',
      'register',
      'forgot-password',
      'reset-password',
      'public-dashboard',
      'overview',
      'realtime',
      'heatmaps',
      'pages-sources',
      'funnels',
      'settings',
      'not-found',
    ]) {
      expect(names).toContain(name)
    }
  })

  it('maps the spec routes to the expected paths', () => {
    expect(router.resolve({ name: 'heatmaps' }).path).toBe('/behaviour/heatmaps')
    expect(router.resolve({ name: 'pages-sources' }).path).toBe('/pages-sources')
    expect(router.resolve({ name: 'reset-password' }).path).toBe('/reset-password')
    expect(router.resolve({ name: 'public-dashboard', params: { token: 'abc' } }).path).toBe(
      '/share/abc',
    )
  })
})

describe('auth guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    refresh.mockReset()
    // Default to no recoverable session unless a test opts in.
    refresh.mockRejectedValue(new Error('no session'))
  })

  it('redirects an unauthenticated user from a protected route to login', async () => {
    await router.push('/realtime')
    await router.isReady()
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/realtime')
  })

  it('allows an authenticated user into protected routes', async () => {
    // The boot refresh runs first; a valid cookie keeps the session alive.
    refresh.mockResolvedValue({ access_token: 'jwt', expires_in: 900 })
    const auth = useAuthStore()
    auth.setSession('jwt', 900)
    await router.push('/funnels')
    await router.isReady()
    expect(router.currentRoute.value.name).toBe('funnels')
  })

  it('lets anyone reach the public dashboard', async () => {
    await router.push('/share/tok')
    await router.isReady()
    expect(router.currentRoute.value.name).toBe('public-dashboard')
  })
})

describe('boot-time silent refresh', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    refresh.mockReset()
  })

  it('restores the session before the guard resolves so a protected route survives reload', async () => {
    refresh.mockResolvedValue({ access_token: 'restored-jwt', expires_in: 900 })

    await router.push('/funnels')
    await router.isReady()

    expect(refresh).toHaveBeenCalledTimes(1)
    expect(useAuthStore().isAuthenticated).toBe(true)
    expect(router.currentRoute.value.name).toBe('funnels')
  })

  it('falls back to login when the refresh cookie is gone', async () => {
    refresh.mockRejectedValue(new Error('expired'))

    // Use a protected route distinct from the previous test's destination so
    // this is a real navigation (an identical push is a no-op that would skip
    // the guard), then assert the guard redirected to login.
    await router.push('/realtime')
    await router.isReady()

    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('runs the boot refresh at most once across navigations', async () => {
    refresh.mockResolvedValue({ access_token: 'restored-jwt', expires_in: 900 })

    await router.push('/funnels')
    await router.isReady()
    await router.push('/realtime')
    await router.isReady()

    expect(refresh).toHaveBeenCalledTimes(1)
  })
})
