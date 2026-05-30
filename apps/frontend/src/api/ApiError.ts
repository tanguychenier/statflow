// =============================================================================
// Statflow Dashboard — Typed API error
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { FieldError, ProblemDetails } from './types'

/**
 * A normalised error thrown by the HTTP client for every non-2xx response.
 *
 * The backend speaks RFC 9457 (`application/problem+json`). We surface the
 * machine-readable `type` URI as `code` so callers can branch on a stable slug
 * (e.g. `https://statflow.io/errors/validation-failed`) rather than parsing
 * human strings. Network/parse failures produce a synthetic error with
 * `status === 0`.
 */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly title: string
  readonly detail?: string
  readonly traceId?: string
  readonly fieldErrors: FieldError[]
  readonly retryAfter?: number

  constructor(params: {
    status: number
    code: string
    title: string
    detail?: string
    traceId?: string
    fieldErrors?: FieldError[]
    retryAfter?: number
  }) {
    super(params.detail ?? params.title)
    this.name = 'ApiError'
    this.status = params.status
    this.code = params.code
    this.title = params.title
    this.detail = params.detail
    this.traceId = params.traceId
    this.fieldErrors = params.fieldErrors ?? []
    this.retryAfter = params.retryAfter
  }

  static fromProblem(problem: ProblemDetails, retryAfter?: number): ApiError {
    return new ApiError({
      status: problem.status,
      code: problem.type,
      title: problem.title,
      detail: problem.detail,
      traceId: problem.trace_id,
      fieldErrors: problem.errors,
      retryAfter,
    })
  }

  static network(message: string): ApiError {
    return new ApiError({ status: 0, code: 'network-error', title: message })
  }

  /** Authentication failures the SPA may recover from by refreshing the token. */
  get isUnauthorized(): boolean {
    return this.status === 401
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  /** True for errors the caller may safely retry after a delay. */
  get isRetryable(): boolean {
    return this.status === 0 || this.status === 429 || this.status === 503 || this.status === 504
  }
}
