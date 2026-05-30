// =============================================================================
// Statflow Dashboard — Account composables (current user, teams, API keys)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { usersApi } from '../endpoints/users'
import { apiKeysApi, teamsApi } from '../endpoints/sites'
import { queryKeys } from '../queryKeys'
import type {
  ApiKeyCreateRequest,
  ChangePasswordRequest,
  InviteTeamMemberRequest,
  TeamCreateRequest,
  UpdateTeamMemberRoleRequest,
  UserUpdateRequest,
} from '../types'

export function useCurrentUser(options: { enabled?: MaybeRefOrGetter<boolean> } = {}) {
  return useQuery({
    queryKey: queryKeys.currentUser(),
    queryFn: () => usersApi.me(),
    enabled: computed(() => (options.enabled ? Boolean(toValue(options.enabled)) : true)),
    staleTime: 300_000,
  })
}

export function useUpdateProfile() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: UserUpdateRequest) => usersApi.updateMe(body),
    onSuccess: (user) => qc.setQueryData(queryKeys.currentUser(), user),
  })
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (body: ChangePasswordRequest) => usersApi.changePassword(body),
  })
}

export function useTeams() {
  return useQuery({
    queryKey: queryKeys.teams(),
    queryFn: () => teamsApi.list(),
    staleTime: 300_000,
  })
}

export function useTeamMembers(teamId: MaybeRefOrGetter<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.teamMembers(toValue(teamId) ?? '')),
    queryFn: () => teamsApi.listMembers(toValue(teamId) as string),
    enabled: computed(() => Boolean(toValue(teamId))),
  })
}

export function useCreateTeam() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: TeamCreateRequest) => teamsApi.create(body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.teams() }),
  })
}

export function useInviteTeamMember(teamId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: InviteTeamMemberRequest) => teamsApi.invite(toValue(teamId), body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.teamMembers(toValue(teamId)) }),
  })
}

export function useUpdateTeamMemberRole(teamId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { userId: string } & UpdateTeamMemberRoleRequest) =>
      teamsApi.updateMemberRole(toValue(teamId), payload.userId, { role: payload.role }),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.teamMembers(toValue(teamId)) }),
  })
}

export function useRemoveTeamMember(teamId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userId: string) => teamsApi.removeMember(toValue(teamId), userId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.teamMembers(toValue(teamId)) }),
  })
}

export function useApiKeys() {
  return useQuery({
    queryKey: queryKeys.apiKeys(),
    queryFn: () => apiKeysApi.list(),
  })
}

export function useCreateApiKey() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: ApiKeyCreateRequest) => apiKeysApi.create(body),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.apiKeys() }),
  })
}

export function useRevokeApiKey() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (keyId: string) => apiKeysApi.revoke(keyId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.apiKeys() }),
  })
}
