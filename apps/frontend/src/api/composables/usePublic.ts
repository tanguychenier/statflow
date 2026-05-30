// =============================================================================
// Statflow Dashboard — Public shared dashboard composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { publicApi } from '../endpoints/analytics'
import { queryKeys } from '../queryKeys'
import type { PublicOverviewParams } from '../types'

export function usePublicOverview(
  token: MaybeRefOrGetter<string | null>,
  params: MaybeRefOrGetter<PublicOverviewParams> = () => ({}),
) {
  return useQuery({
    queryKey: computed(() => {
      const p = toValue(params)
      return queryKeys.publicOverview(toValue(token) ?? '', p.date_from, p.date_to)
    }),
    queryFn: () => publicApi.overview(toValue(token) as string, toValue(params)),
    enabled: computed(() => Boolean(toValue(token))),
    retry: false,
  })
}
