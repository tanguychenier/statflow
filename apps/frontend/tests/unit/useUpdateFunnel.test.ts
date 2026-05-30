// =============================================================================
// useUpdateFunnel — mutation wiring tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// vue-query's useMutation/useQueryClient are mocked so the test asserts the
// composable's contract: it calls the PATCH endpoint and invalidates both the
// funnel list and the individual funnel cache keys on success.
// =============================================================================

import { describe, it, expect, vi, beforeEach } from 'vitest'

const update = vi.fn().mockResolvedValue({ id: 'f1' })
const invalidateQueries = vi.fn()

// Capture the options object passed to useMutation so we can invoke its hooks.
let capturedOptions: {
  mutationFn: (vars: unknown) => unknown
  onSuccess: (data: unknown, vars: unknown) => void
}

vi.mock('@tanstack/vue-query', () => ({
  useQueryClient: () => ({ invalidateQueries }),
  useMutation: (options: typeof capturedOptions) => {
    capturedOptions = options
    return { mutate: vi.fn(), mutateAsync: vi.fn() }
  },
}))

vi.mock('@/api', () => ({
  funnelsApi: { update: (...args: unknown[]) => update(...args) },
  queryKeys: {
    funnels: (siteId: string) => ['sites', siteId, 'funnels'],
    funnel: (siteId: string, funnelId: string) => ['sites', siteId, 'funnels', funnelId],
  },
}))

import { useUpdateFunnel } from '@/components/funnels/useUpdateFunnel'

describe('useUpdateFunnel', () => {
  beforeEach(() => {
    update.mockClear()
    invalidateQueries.mockClear()
  })

  it('calls the PATCH endpoint with the site, funnel id and body', async () => {
    useUpdateFunnel('s1')
    const body = { name: 'Renamed', steps: [] }
    await capturedOptions.mutationFn({ funnelId: 'f1', body })
    expect(update).toHaveBeenCalledWith('s1', 'f1', body)
  })

  it('invalidates the list and the individual funnel on success', () => {
    useUpdateFunnel('s1')
    capturedOptions.onSuccess(undefined, { funnelId: 'f1', body: { name: 'x', steps: [] } })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['sites', 's1', 'funnels'] })
    expect(invalidateQueries).toHaveBeenCalledWith({
      queryKey: ['sites', 's1', 'funnels', 'f1'],
    })
  })

  it('resolves the site id from a getter', async () => {
    useUpdateFunnel(() => 's2')
    await capturedOptions.mutationFn({ funnelId: 'f9', body: { name: 'y', steps: [] } })
    expect(update).toHaveBeenCalledWith('s2', 'f9', { name: 'y', steps: [] })
  })
})
