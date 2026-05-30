// =============================================================================
// Statflow Dashboard — Reporting composables (reports, alerts, exports)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { alertsApi, exportsApi, reportsApi, scheduledReportsApi } from '../endpoints/reporting'
import { queryKeys } from '../queryKeys'
import type {
  AlertCreateRequest,
  ExportCreateRequest,
  SavedReportCreateRequest,
  ScheduledReportCreateRequest,
} from '../types'

export function useSavedReports(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.savedReports(toValue(siteId) ?? '')),
    queryFn: () => reportsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateSavedReport(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: SavedReportCreateRequest) => reportsApi.create(toValue(siteId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.savedReports(toValue(siteId)) }),
  })
}

export function useScheduledReports(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.scheduledReports(toValue(siteId) ?? '')),
    queryFn: () => scheduledReportsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateScheduledReport(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: ScheduledReportCreateRequest) =>
      scheduledReportsApi.create(toValue(siteId), body),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.scheduledReports(toValue(siteId)) }),
  })
}

export function useAlerts(siteId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.alerts(toValue(siteId) ?? '')),
    queryFn: () => alertsApi.list(toValue(siteId) as string),
    enabled: computed(() => Boolean(toValue(siteId))),
  })
}

export function useCreateAlert(siteId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: AlertCreateRequest) => alertsApi.create(toValue(siteId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.alerts(toValue(siteId)) }),
  })
}

export function useCreateExport(siteId: MaybeRefOrGetter<string>) {
  return useMutation({
    mutationFn: (body: ExportCreateRequest) => exportsApi.create(toValue(siteId), body),
  })
}
