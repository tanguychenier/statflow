<script setup lang="ts">
// =============================================================================
// SettingsView — site configuration screen (screens.md §6)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Orchestrates the six settings sub-sections behind a tab navigation: General,
// Tracking (snippet + collection + retention + lists), Team & roles, and the
// Danger Zone. Data comes from the typed composables (sites/settings/teams/
// members); every mutation runs here with toast feedback while the presentation
// and all rules live in the (separately tested) settings components & model.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import {
  GeneralSection,
  TrackerSnippetCard,
  TrackingSection,
  TeamSection,
  DangerZone,
  canManageSite,
  canDeleteSite,
  useDeleteSite,
} from '@/components/settings'
import { Tabs } from '@/components/ui/tabs'
import { SkeletonCard } from '@/components/ui/skeleton'
import { EmptyState } from '@/components/ui/empty-state'
import { useCurrentSiteStore } from '@/stores/currentSite'
import {
  useSite,
  useSiteSettings,
  useUpdateSite,
  useUpdateSiteSettings,
  useRotateTrackerKey,
} from '@/api/composables/useSites'
import {
  useTeams,
  useTeamMembers,
  useInviteTeamMember,
  useUpdateTeamMemberRole,
  useRemoveTeamMember,
} from '@/api/composables/useAccount'
import { toast } from '@/composables/useToast'
import type {
  InviteTeamMemberRequest,
  SiteSettings,
  SiteUpdateRequest,
  TeamMember,
  TeamRole,
} from '@/api/types'

const { t } = useI18n()

const siteStore = useCurrentSiteStore()
const { currentSiteId } = storeToRefs(siteStore)
const siteId = computed(() => currentSiteId.value ?? '')

// ── Queries ──────────────────────────────────────────────────────────────────
const site = useSite(currentSiteId)
const settings = useSiteSettings(currentSiteId)
const teams = useTeams()

const teamId = computed(() => site.data.value?.team_id ?? null)
const team = computed(
  () => teams.data.value?.data.find((candidate) => candidate.id === teamId.value) ?? null,
)
const currentRole = computed<TeamRole | null>(() => team.value?.current_user_role ?? null)
const canManage = computed(() => canManageSite(currentRole.value))
const canDelete = computed(() => canDeleteSite(currentRole.value))

const members = useTeamMembers(teamId)
const memberItems = computed(() => members.data.value?.data ?? [])

// ── Mutations ─────────────────────────────────────────────────────────────────
const updateSite = useUpdateSite(siteId)
const updateSettings = useUpdateSiteSettings(siteId)
const rotateKey = useRotateTrackerKey(siteId)
const inviteMember = useInviteTeamMember(computed(() => teamId.value ?? ''))
const changeRole = useUpdateTeamMemberRole(computed(() => teamId.value ?? ''))
const removeMember = useRemoveTeamMember(computed(() => teamId.value ?? ''))
const deleteSite = useDeleteSite()

// The tracker is served from the operator's own instance (no CDN, ADR-0009),
// so the install snippet points at this dashboard's own origin.
const trackerOrigin = computed(() => window.location.origin)
const lastRotation = ref<string | null>(null)

const tabs = computed(() => [
  { value: 'general', label: t('settings.sections.general') },
  { value: 'tracking', label: t('settings.sections.tracking') },
  { value: 'team', label: t('settings.sections.team') },
  ...(canManage.value ? [{ value: 'danger', label: t('settings.sections.dangerZone') }] : []),
])
const activeTab = ref('general')

// With no site selected the site-scoped queries stay disabled (isPending stays
// true but isLoading is false), so the empty state is keyed on the selection.
const noSite = computed(() => !currentSiteId.value)
const isLoading = computed(
  () => !noSite.value && (site.isLoading.value || settings.isLoading.value),
)
const hasError = computed(() => !noSite.value && (site.isError.value || settings.isError.value))

// ── Handlers ──────────────────────────────────────────────────────────────────
async function onSaveGeneral(body: SiteUpdateRequest) {
  try {
    await updateSite.mutateAsync(body)
    toast.success(t('settings.toast.saved'))
  } catch {
    toast.error(t('errors.saveFailed'))
  }
}

async function onSaveTracking(body: SiteSettings) {
  try {
    await updateSettings.mutateAsync(body)
    toast.success(t('settings.toast.saved'))
  } catch {
    toast.error(t('errors.saveFailed'))
  }
}

async function onRotateKey() {
  try {
    const result = await rotateKey.mutateAsync()
    lastRotation.value = result.old_key_valid_until
    toast.success(t('settings.toast.keyRotated'))
  } catch {
    toast.error(t('errors.generic'))
  }
}

async function onInvite(body: InviteTeamMemberRequest) {
  try {
    await inviteMember.mutateAsync(body)
    toast.success(t('settings.toast.invited', { email: body.email }))
  } catch {
    toast.error(t('errors.generic'))
  }
}

async function onChangeRole(payload: { userId: string; role: TeamRole }) {
  try {
    await changeRole.mutateAsync(payload)
    toast.success(t('settings.toast.roleUpdated'))
  } catch {
    toast.error(t('errors.generic'))
  }
}

async function onRemoveMember(member: TeamMember) {
  try {
    await removeMember.mutateAsync(member.user_id)
    toast.success(t('settings.toast.memberRemoved'))
  } catch {
    toast.error(t('errors.generic'))
  }
}

async function onDeleteSite() {
  if (!site.data.value) return
  const removedId = site.data.value.id
  try {
    await deleteSite.mutateAsync(removedId)
    siteStore.setSites(siteStore.sites.filter((entry) => entry.id !== removedId))
    toast.success(t('settings.toast.siteDeleted'))
  } catch {
    toast.error(t('errors.generic'))
  }
}

// No dedicated reset-data endpoint exists yet; surface intent without a no-op
// network call so the action is explicit rather than silently failing.
function onResetData() {
  toast.warning(t('settings.toast.resetUnavailable'))
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold tracking-tight text-fg-primary">{{ t('settings.title') }}</h1>

    <div v-if="isLoading" class="flex flex-col gap-4" aria-busy="true">
      <SkeletonCard v-for="i in 2" :key="i" />
    </div>

    <EmptyState
      v-else-if="hasError"
      variant="error"
      :title="t('errors.loadFailed')"
      :action-label="t('common.retry')"
      @action="() => { site.refetch(); settings.refetch() }"
    />

    <EmptyState
      v-else-if="noSite"
      variant="no-setup"
      :title="t('settings.noSite.title')"
      :description="t('settings.noSite.description')"
    />

    <Tabs v-else-if="site.data.value && settings.data.value" v-model="activeTab" :items="tabs">
      <template #general>
        <div class="flex flex-col gap-6">
          <GeneralSection
            :site="site.data.value"
            :saving="updateSite.isPending.value"
            :readonly="!canManage"
            @save="onSaveGeneral"
          />
          <TrackerSnippetCard
            :site="site.data.value"
            :origin="trackerOrigin"
            :rotating="rotateKey.isPending.value"
            :old-key-valid-until="lastRotation"
            :readonly="!canManage"
            @rotate="onRotateKey"
          />
        </div>
      </template>

      <template #tracking>
        <TrackingSection
          :settings="settings.data.value"
          :saving="updateSettings.isPending.value"
          :readonly="!canManage"
          @save="onSaveTracking"
        />
      </template>

      <template #team>
        <TeamSection
          :members="memberItems"
          :loading="members.isPending.value"
          :error="members.isError.value"
          :can-manage="canManage"
          :inviting="inviteMember.isPending.value"
          @invite="onInvite"
          @change-role="onChangeRole"
          @remove="onRemoveMember"
          @retry="members.refetch()"
        />
      </template>

      <template #danger>
        <DangerZone
          :site="site.data.value"
          :can-reset="canManage"
          :can-delete="canDelete"
          :resetting="false"
          :deleting="deleteSite.isPending.value"
          @reset="onResetData"
          @delete="onDeleteSite"
        />
      </template>
    </Tabs>
  </div>
</template>
