// =============================================================================
// Statflow Dashboard — Reporting endpoints (saved/scheduled reports, alerts, exports)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { http } from '../http'
import type {
  Alert,
  AlertCreateRequest,
  AlertUpdateRequest,
  Export,
  ExportCreateRequest,
  PaginatedResponse,
  SavedReport,
  SavedReportCreateRequest,
  ScheduledReport,
  ScheduledReportCreateRequest,
  ScheduledReportUpdateRequest,
} from '../types'

export const reportsApi = {
  list: (siteId: string, params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<SavedReport>>(`/sites/${siteId}/reports`, { query: params }),
  get: (siteId: string, reportId: string) =>
    http.get<SavedReport>(`/sites/${siteId}/reports/${reportId}`),
  create: (siteId: string, body: SavedReportCreateRequest) =>
    http.post<SavedReport>(`/sites/${siteId}/reports`, body),
  remove: (siteId: string, reportId: string) =>
    http.delete<void>(`/sites/${siteId}/reports/${reportId}`),
}

export const scheduledReportsApi = {
  list: (siteId: string, params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<ScheduledReport>>(`/sites/${siteId}/scheduled-reports`, {
      query: params,
    }),
  create: (siteId: string, body: ScheduledReportCreateRequest) =>
    http.post<ScheduledReport>(`/sites/${siteId}/scheduled-reports`, body),
  update: (siteId: string, id: string, body: ScheduledReportUpdateRequest) =>
    http.patch<ScheduledReport>(`/sites/${siteId}/scheduled-reports/${id}`, body),
  remove: (siteId: string, id: string) =>
    http.delete<void>(`/sites/${siteId}/scheduled-reports/${id}`),
}

export const alertsApi = {
  list: (siteId: string, params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<Alert>>(`/sites/${siteId}/alerts`, { query: params }),
  create: (siteId: string, body: AlertCreateRequest) =>
    http.post<Alert>(`/sites/${siteId}/alerts`, body),
  update: (siteId: string, alertId: string, body: AlertUpdateRequest) =>
    http.patch<Alert>(`/sites/${siteId}/alerts/${alertId}`, body),
  remove: (siteId: string, alertId: string) =>
    http.delete<void>(`/sites/${siteId}/alerts/${alertId}`),
}

export const exportsApi = {
  create: (siteId: string, body: ExportCreateRequest) =>
    http.post<Export>(`/sites/${siteId}/exports`, body),
  status: (siteId: string, exportId: string) =>
    http.get<Export>(`/sites/${siteId}/exports/${exportId}`),
}
