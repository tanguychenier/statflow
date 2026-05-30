// =============================================================================
// Statflow Dashboard — ApiError tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { ApiError } from '@/api/ApiError'
import type { ProblemDetails } from '@/api/types'

describe('ApiError', () => {
  const problem: ProblemDetails = {
    type: 'https://statflow.io/errors/validation-failed',
    title: 'Validation Failed',
    status: 422,
    detail: 'data_retention_days out of range',
    trace_id: '01HXK2Q4B7T8NMPZW6RDYGJ5FM',
    errors: [{ field: 'data_retention_days', code: 'out_of_range', message: 'too big' }],
  }

  it('maps a problem document onto its fields', () => {
    const error = ApiError.fromProblem(problem, 30)
    expect(error.status).toBe(422)
    expect(error.code).toBe('https://statflow.io/errors/validation-failed')
    expect(error.title).toBe('Validation Failed')
    expect(error.detail).toBe('data_retention_days out of range')
    expect(error.traceId).toBe('01HXK2Q4B7T8NMPZW6RDYGJ5FM')
    expect(error.fieldErrors).toHaveLength(1)
    expect(error.retryAfter).toBe(30)
    expect(error.message).toBe('data_retention_days out of range')
  })

  it('falls back to the title when no detail is present', () => {
    const error = ApiError.fromProblem({ ...problem, detail: undefined })
    expect(error.message).toBe('Validation Failed')
  })

  it('builds a synthetic network error with status 0', () => {
    const error = ApiError.network('offline')
    expect(error.status).toBe(0)
    expect(error.code).toBe('network-error')
    expect(error.isRetryable).toBe(true)
  })

  it('classifies unauthorized and validation', () => {
    expect(ApiError.fromProblem({ ...problem, status: 401 }).isUnauthorized).toBe(true)
    expect(ApiError.fromProblem(problem).isValidation).toBe(true)
    expect(ApiError.fromProblem({ ...problem, status: 403 }).isUnauthorized).toBe(false)
  })

  it.each([429, 503, 504])('treats %i as retryable', (status) => {
    expect(ApiError.fromProblem({ ...problem, status }).isRetryable).toBe(true)
  })

  it('treats 4xx (except those above) as non-retryable', () => {
    expect(ApiError.fromProblem({ ...problem, status: 400 }).isRetryable).toBe(false)
  })

  it('defaults fieldErrors to an empty array', () => {
    const error = new ApiError({ status: 500, code: 'x', title: 'y' })
    expect(error.fieldErrors).toEqual([])
  })
})
