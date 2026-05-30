// =============================================================================
// Statflow Dashboard — Sites, teams & API keys endpoints
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { http } from '../http'
import type {
  ApiKey,
  ApiKeyCreateRequest,
  ApiKeyWithSecret,
  InviteTeamMemberRequest,
  ListSitesParams,
  PaginatedResponse,
  RotateTrackerKeyResponse,
  Site,
  SiteCreateRequest,
  SiteSettings,
  SiteUpdateRequest,
  Team,
  TeamCreateRequest,
  TeamMember,
  TeamUpdateRequest,
  UpdateTeamMemberRoleRequest,
} from '../types'

export const sitesApi = {
  list: (params: ListSitesParams = {}) =>
    http.get<PaginatedResponse<Site>>('/sites', { query: params as Record<string, unknown> }),
  get: (siteId: string) => http.get<Site>(`/sites/${siteId}`),
  create: (body: SiteCreateRequest) => http.post<Site>('/sites', body),
  update: (siteId: string, body: SiteUpdateRequest) => http.patch<Site>(`/sites/${siteId}`, body),
  remove: (siteId: string) => http.delete<void>(`/sites/${siteId}`),

  getSettings: (siteId: string) => http.get<SiteSettings>(`/sites/${siteId}/settings`),
  updateSettings: (siteId: string, body: SiteSettings) =>
    http.put<SiteSettings>(`/sites/${siteId}/settings`, body),
  rotateTrackerKey: (siteId: string) =>
    http.post<RotateTrackerKeyResponse>(`/sites/${siteId}/tracker-key/rotate`),
}

export const teamsApi = {
  list: (params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<Team>>('/teams', { query: params }),
  get: (teamId: string) => http.get<Team>(`/teams/${teamId}`),
  create: (body: TeamCreateRequest) => http.post<Team>('/teams', body),
  update: (teamId: string, body: TeamUpdateRequest) => http.patch<Team>(`/teams/${teamId}`, body),
  remove: (teamId: string) => http.delete<void>(`/teams/${teamId}`),

  listMembers: (teamId: string, params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<TeamMember>>(`/teams/${teamId}/members`, { query: params }),
  invite: (teamId: string, body: InviteTeamMemberRequest) =>
    http.post<TeamMember>(`/teams/${teamId}/members`, body),
  updateMemberRole: (teamId: string, userId: string, body: UpdateTeamMemberRoleRequest) =>
    http.patch<TeamMember>(`/teams/${teamId}/members/${userId}`, body),
  removeMember: (teamId: string, userId: string) =>
    http.delete<void>(`/teams/${teamId}/members/${userId}`),
}

export const apiKeysApi = {
  list: (params?: { cursor?: string; limit?: number }) =>
    http.get<PaginatedResponse<ApiKey>>('/api-keys', { query: params }),
  create: (body: ApiKeyCreateRequest) => http.post<ApiKeyWithSecret>('/api-keys', body),
  revoke: (keyId: string) => http.delete<void>(`/api-keys/${keyId}`),
}
