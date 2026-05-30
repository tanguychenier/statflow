// =============================================================================
// Statflow Dashboard — Sites, goals, funnels, segments composables
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { sitesApi } from '../endpoints/sites'
import { funnelsApi, goalsApi, segmentsApi } from '../endpoints/analytics'
import { queryKeys } from '../queryKeys'
import type {
  FunnelCreateRequest,
  GoalCreateRequest,
  ListSitesParams,
  SegmentCreateRequest,
  SiteCreateRequest,
  SiteSettings,
  SiteUpdateRequest,
} from '../types'

export function useSites(params: MaybeRefOrGetter<ListSitesParams> = () => ({})) {
  return useQuery({
    queryKey: computed(() => queryKeys.sites(toValue(params))),
    queryFn: () => sitesApi.list(toValue(params)),
    staleTime: 300_000,
  })
}

export function useSite(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.site(toValue(siteId) ?? '')),
    queryFn: () => sitesApi.get(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useSiteSettings(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.siteSettings(toValue(siteId) ?? '')),
    queryFn: () => sitesApi.getSettings(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateSite() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: SiteCreateRequest) => sitesApi.create(body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.sitesList() }),
  })
}

export function useUpdateSite(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: SiteUpdateRequest) => sitesApi.update(toValue(siteId), body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.site(toValue(siteId)) })
      qc.invalidateQueries({ queryKey: queryKeys.sitesList() })
    },
  })
}

export function useUpdateSiteSettings(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: SiteSettings) => sitesApi.updateSettings(toValue(siteId), body),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.siteSettings(toValue(siteId)) }),
  })
}

export function useRotateTrackerKey(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => sitesApi.rotateTrackerKey(toValue(siteId)),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.site(toValue(siteId)) }),
  })
}

// ── Goals ──────────────────────────────────────────────────────────────────

export function useGoals(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.goals(toValue(siteId) ?? '')),
    queryFn: () => goalsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateGoal(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: GoalCreateRequest) => goalsApi.create(toValue(siteId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.goals(toValue(siteId)) }),
  })
}

export function useDeleteGoal(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (goalId: string) => goalsApi.remove(toValue(siteId), goalId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.goals(toValue(siteId)) }),
  })
}

// ── Funnels (persisted definitions) ──────────────────────────────────────────

export function useFunnels(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.funnels(toValue(siteId) ?? '')),
    queryFn: () => funnelsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateFunnel(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: FunnelCreateRequest) => funnelsApi.create(toValue(siteId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.funnels(toValue(siteId)) }),
  })
}

export function useDeleteFunnel(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (funnelId: string) => funnelsApi.remove(toValue(siteId), funnelId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.funnels(toValue(siteId)) }),
  })
}

// ── Segments ─────────────────────────────────────────────────────────────────

export function useSegments(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.segments(toValue(siteId) ?? '')),
    queryFn: () => segmentsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateSegment(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: SegmentCreateRequest) => segmentsApi.create(toValue(siteId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.segments(toValue(siteId)) }),
  })
}

export function useDeleteSegment(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (segmentId: string) => segmentsApi.remove(toValue(siteId), segmentId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.segments(toValue(siteId)) }),
  })
}
