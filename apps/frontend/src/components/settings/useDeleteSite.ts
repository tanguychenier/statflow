// =============================================================================
// Settings — delete-site mutation (screen-local)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The shared composable library ships create/update/settings for sites but not
// delete, so the Danger Zone wires the DELETE endpoint here. It invalidates the
// sites list so the property switcher drops the removed site immediately.
// =============================================================================

import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { sitesApi } from '@/api'

export function useDeleteSite() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (siteId: string) => sitesApi.remove(siteId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sites'] }),
  })
}
