// =============================================================================
// useSites — cache-invalidation wiring tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// vue-query is mocked so each mutation's onSuccess hook can be invoked in
// isolation. The real queryKeys registry is used: the point of these tests is
// that create/update invalidate the precise site-LIST key and never the bare
// ['sites'] prefix, which would also wipe settings/goals/funnels/segments.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { queryKeys } from '@/api/queryKeys'

const invalidateQueries = vi.fn()

interface MutationOptions {
  mutationFn: (vars: unknown) => unknown
  onSuccess: (data: unknown, vars: unknown) => void
}

const capturedMutations: MutationOptions[] = []

vi.mock('@tanstack/vue-query', () => ({
  useQueryClient: () => ({ invalidateQueries }),
  useQuery: vi.fn(),
  useMutation: (options: MutationOptions) => {
    capturedMutations.push(options)
    return { mutate: vi.fn(), mutateAsync: vi.fn() }
  },
}))

vi.mock('@/api/endpoints/sites', () => ({
  sitesApi: { create: vi.fn(), update: vi.fn() },
}))

vi.mock('@/api/endpoints/analytics', () => ({
  funnelsApi: {},
  goalsApi: {},
  segmentsApi: {},
}))

import { useCreateSite, useUpdateSite } from '@/api/composables/useSites'

function lastMutation(): MutationOptions {
  return capturedMutations[capturedMutations.length - 1]
}

const invalidatedKeys = () =>
  invalidateQueries.mock.calls.map(([arg]) => (arg as { queryKey: unknown }).queryKey)

describe('useSites cache invalidation', () => {
  beforeEach(() => {
    invalidateQueries.mockClear()
    capturedMutations.length = 0
  })

  it('useCreateSite invalidates only the site-list key, not the bare sites prefix', () => {
    useCreateSite()
    lastMutation().onSuccess(undefined, undefined)
    expect(invalidatedKeys()).toEqual([queryKeys.sitesList()])
    expect(invalidatedKeys()).not.toContainEqual(['sites'])
  })

  it('useUpdateSite invalidates the single site and the site-list key', () => {
    useUpdateSite('s1')
    lastMutation().onSuccess(undefined, undefined)
    expect(invalidatedKeys()).toContainEqual(queryKeys.site('s1'))
    expect(invalidatedKeys()).toContainEqual(queryKeys.sitesList())
    expect(invalidatedKeys()).not.toContainEqual(['sites'])
  })
})
