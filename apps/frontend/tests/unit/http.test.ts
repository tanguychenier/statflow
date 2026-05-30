// =============================================================================
// Statflow Dashboard — HTTP client tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { http, request, configureHttpClient } from '@/api/http'
import { ApiError } from '@/api/ApiError'

function jsonResponse(body: unknown, init: ResponseInit = {}): Response {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
    ...init,
  })
}

describe('http client', () => {
  beforeEach(() => {
    configureHttpClient({
      getAccessToken: () => 'token-123',
      refreshAccessToken: () => Promise.resolve(null),
      onAuthFailure: () => {},
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    configureHttpClient({
      getAccessToken: () => null,
      refreshAccessToken: () => Promise.resolve(null),
      onAuthFailure: () => {},
    })
  })

  it('prefixes the base url and attaches the bearer token', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }))
    vi.stubGlobal('fetch', fetchMock)

    await http.get('/users/me')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/users/me')
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer token-123')
    expect(init.method).toBe('GET')
  })

  it('serialises a JSON body and sets the content type on POST', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ id: '1' }))
    vi.stubGlobal('fetch', fetchMock)

    await http.post('/sites', { name: 'Acme' })

    const [, init] = fetchMock.mock.calls[0]
    expect(init.body).toBe(JSON.stringify({ name: 'Acme' }))
    expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json')
  })

  it('appends defined query parameters and skips nullish ones', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await http.get('/sites', { query: { limit: 20, search: undefined, team_id: 't1' } })

    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('limit=20')
    expect(url).toContain('team_id=t1')
    expect(url).not.toContain('search=')
  })

  it('omits the Authorization header for anonymous requests', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({}))
    vi.stubGlobal('fetch', fetchMock)

    await request('/auth/login', { method: 'POST', body: {}, anonymous: true })

    const [, init] = fetchMock.mock.calls[0]
    expect((init.headers as Record<string, string>).Authorization).toBeUndefined()
  })

  it('returns undefined for a 204 No Content response', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    const result = await http.delete('/sites/1')
    expect(result).toBeUndefined()
  })

  it('throws a typed ApiError from a problem+json body', async () => {
    const problem = {
      type: 'https://statflow.io/errors/not-found',
      title: 'Not Found',
      status: 404,
      trace_id: 'abc',
    }
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse(problem, { status: 404 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(http.get('/sites/missing')).rejects.toMatchObject({
      status: 404,
      code: 'https://statflow.io/errors/not-found',
    })
  })

  it('wraps a fetch rejection as a network ApiError', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    await expect(http.get('/sites')).rejects.toBeInstanceOf(ApiError)
    await expect(http.get('/sites')).rejects.toMatchObject({ status: 0 })
  })

  it('refreshes the token once on a 401 then replays the request', async () => {
    const refresh = vi.fn().mockResolvedValue('fresh-token')
    configureHttpClient({ refreshAccessToken: refresh })

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockResolvedValueOnce(jsonResponse({ id: '1' }))
    vi.stubGlobal('fetch', fetchMock)

    const result = await http.get<{ id: string }>('/users/me')
    expect(refresh).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(result).toEqual({ id: '1' })
  })

  it('invokes onAuthFailure when the replay is still rejected after a refresh', async () => {
    const onAuthFailure = vi.fn()
    const refresh = vi.fn().mockResolvedValue('fresh-token')
    configureHttpClient({ refreshAccessToken: refresh, onAuthFailure })

    // Both the original request and the replayed one return 401: the refreshed
    // token is itself rejected, so the session is unrecoverable.
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 401 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(http.get('/users/me')).rejects.toBeInstanceOf(ApiError)
    expect(refresh).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(onAuthFailure).toHaveBeenCalledTimes(1)
  })

  it('invokes onAuthFailure when the refresh fails', async () => {
    const onAuthFailure = vi.fn()
    configureHttpClient({
      refreshAccessToken: () => Promise.resolve(null),
      onAuthFailure,
    })
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 401 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(http.get('/users/me')).rejects.toBeInstanceOf(ApiError)
    expect(onAuthFailure).toHaveBeenCalledTimes(1)
  })

  it('reads Retry-After into the error', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse(
        { type: 't', title: 'Rate Limit', status: 429, trace_id: 'x' },
        { status: 429, headers: { 'Content-Type': 'application/json', 'Retry-After': '12' } },
      ),
    )
    vi.stubGlobal('fetch', fetchMock)

    await expect(http.get('/events')).rejects.toMatchObject({ retryAfter: 12 })
  })
})
