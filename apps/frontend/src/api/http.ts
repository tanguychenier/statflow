// =============================================================================
// Statflow Dashboard — HTTP client core
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A thin `fetch` wrapper that:
//  - prefixes every path with the configured API base (VITE_API_BASE_URL)
//  - attaches the bearer access token from a pluggable token provider
//  - parses RFC 9457 problem+json into a typed ApiError
//  - transparently refreshes the access token once on a 401 and replays
//
// It intentionally has no Pinia/Vue dependency: the auth store wires itself in
// via `configureHttpClient`, keeping this module unit-testable in isolation.
// =============================================================================

import { ApiError } from './ApiError'
import type { ProblemDetails } from './types'

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export interface RequestOptions {
  method?: HttpMethod
  /** JSON-serialisable request body. */
  body?: unknown
  /** Query string parameters; `undefined`/`null` values are skipped. */
  query?: Record<string, unknown>
  /** Extra headers merged over the defaults. */
  headers?: Record<string, string>
  /** Skip the Authorization header (used by the public + auth endpoints). */
  anonymous?: boolean
  signal?: AbortSignal
}

interface HttpClientHooks {
  /** Returns the current access token, or null when signed out. */
  getAccessToken: () => string | null
  /** Attempts a token refresh; resolves to a new token or null on failure. */
  refreshAccessToken: () => Promise<string | null>
  /** Called when authentication is unrecoverable (refresh failed). */
  onAuthFailure: () => void
}

const DEFAULT_HOOKS: HttpClientHooks = {
  getAccessToken: () => null,
  refreshAccessToken: () => Promise.resolve(null),
  onAuthFailure: () => {},
}

let hooks: HttpClientHooks = DEFAULT_HOOKS

/** Wire the auth store into the client. Called once during app bootstrap. */
export function configureHttpClient(next: Partial<HttpClientHooks>): void {
  hooks = { ...hooks, ...next }
}

/**
 * The API base every request is prefixed with.
 *
 * In dev we always go through the Vite dev-server proxy at the relative
 * `/api/v1` path: requests stay same-origin, so there is no CORS and no
 * preflight. A configured VITE_API_BASE_URL is deliberately ignored in dev so a
 * stale absolute value in the environment can't reintroduce cross-origin calls.
 *
 * In a production build there is no proxy, so the absolute VITE_API_BASE_URL is
 * honoured (falling back to a relative `/api/v1` when the SPA is served from the
 * same origin as the API).
 */
export function apiBaseUrl(): string {
  if (import.meta.env.DEV) return '/api/v1'
  const configured = import.meta.env.VITE_API_BASE_URL
  return (configured ?? '/api/v1').replace(/\/$/, '')
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const normalisedPath = path.startsWith('/') ? path : `/${path}`
  const url = `${apiBaseUrl()}${normalisedPath}`
  if (!query) return url
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null) search.append(key, String(value))
  }
  const qs = search.toString()
  return qs ? `${url}?${qs}` : url
}

function isProblemDetails(value: unknown): value is ProblemDetails {
  return (
    typeof value === 'object' &&
    value !== null &&
    'status' in value &&
    'title' in value &&
    'type' in value
  )
}

async function toApiError(response: Response): Promise<ApiError> {
  const retryAfterHeader = response.headers.get('Retry-After')
  const retryAfter = retryAfterHeader ? Number(retryAfterHeader) : undefined
  try {
    const payload = (await response.json()) as unknown
    if (isProblemDetails(payload)) {
      return ApiError.fromProblem(payload, Number.isNaN(retryAfter) ? undefined : retryAfter)
    }
  } catch {
    // Body was empty or not JSON — fall through to a synthetic error.
  }
  return new ApiError({
    status: response.status,
    code: `http-${response.status}`,
    title: response.statusText || 'Request failed',
    retryAfter: Number.isNaN(retryAfter as number) ? undefined : retryAfter,
  })
}

async function parseBody<T>(response: Response): Promise<T> {
  if (response.status === 204 || response.headers.get('Content-Length') === '0') {
    return undefined as T
  }
  const text = await response.text()
  if (!text) return undefined as T
  return JSON.parse(text) as T
}

async function executeRequest(path: string, options: RequestOptions): Promise<Response> {
  const { method = 'GET', body, query, headers = {}, anonymous, signal } = options

  const requestHeaders: Record<string, string> = {
    Accept: 'application/json',
    ...headers,
  }

  if (body !== undefined && !('Content-Type' in requestHeaders)) {
    requestHeaders['Content-Type'] = 'application/json'
  }

  if (!anonymous) {
    const token = hooks.getAccessToken()
    if (token) requestHeaders.Authorization = `Bearer ${token}`
  }

  return fetch(buildUrl(path, query), {
    method,
    headers: requestHeaders,
    body: body === undefined ? undefined : JSON.stringify(body),
    credentials: 'include', // refresh-token cookie travels with auth requests
    signal,
  })
}

/**
 * Perform a typed request. On a 401 for an authenticated call we attempt a
 * single token refresh and replay; a second 401 triggers `onAuthFailure`.
 */
export async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  let response: Response
  try {
    response = await executeRequest(path, options)
  } catch (cause) {
    throw ApiError.network(cause instanceof Error ? cause.message : 'Network request failed')
  }

  if (response.status === 401 && !options.anonymous) {
    const refreshed = await hooks.refreshAccessToken()
    if (refreshed) {
      response = await executeRequest(path, options)
    }
    // A failed refresh, or a replay that is still rejected, means the session is
    // unrecoverable — surface it so the app can route the user back to login.
    if (!refreshed || response.status === 401) {
      hooks.onAuthFailure()
    }
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  return parseBody<T>(response)
}

export const http = {
  get: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, 'method'>) =>
    request<T>(path, { ...options, method: 'POST', body }),
  put: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, 'method'>) =>
    request<T>(path, { ...options, method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, 'method'>) =>
    request<T>(path, { ...options, method: 'PATCH', body }),
  delete: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'DELETE' }),
}
