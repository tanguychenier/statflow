// =============================================================================
// Funnels — update mutation (screen-local)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The shared composable library ships create/list/delete for funnels but not
// update, so the Edit flow wires the PATCH endpoint here. It mirrors the shared
// useCreateFunnel contract exactly (same cache key invalidation) so behaviour is
// consistent regardless of which mutation ran.
// =============================================================================

import { toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { funnelsApi, queryKeys } from '@/api'
import type { FunnelCreateRequest } from '@/api/types'

export function useUpdateFunnel(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ funnelId, body }: { funnelId: string; body: FunnelCreateRequest }) =>
      funnelsApi.update(toValue(siteId), funnelId, body),
    onSuccess: (_data, variables) => {
      qc.invalidateQueries({ queryKey: queryKeys.funnels(toValue(siteId)) })
      qc.invalidateQueries({ queryKey: queryKeys.funnel(toValue(siteId), variables.funnelId) })
    },
  })
}
